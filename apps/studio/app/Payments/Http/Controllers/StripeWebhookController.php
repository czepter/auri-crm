<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Jobs\ProcessStripeWebhookEvent;
use App\Payments\Models\StripeEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * M-05: the durable, replay-safe counterpart to M-04's own provisional
 * ConfirmInstallmentCheckoutSession (the client's synchronous return-trip
 * confirm, kept as a same-page UX speedup — see that class's own doc
 * comment). This route is the one Stripe itself calls, whenever it wants,
 * however many times a delivery gets retried, and is the source of truth
 * once it exists.
 *
 * Registered only on the `api` surface (routes/api.php, App\Support\Surface)
 * — the same isolation the health probe already established: a route the
 * public internet reaches at all belongs in the process that carries
 * nothing else, never beside a staff or client session.
 *
 * Verifies the raw body against config('services.stripe.webhook_secret')
 * via stripe-php's own Webhook::constructEvent() BEFORE anything here
 * treats the payload as data — a bad signature never reaches a query, a
 * job, or even JSON decoding on purpose. $request->getContent() returns the
 * exact bytes Stripe signed; nothing upstream of this action needs
 * excluding to get that, because neither the `api` middleware group this
 * surface registers under (App\Support\Surface::registerRoutes(); no CSRF —
 * that only ever rides on `web`) nor anything else in this app's middleware
 * stack reads or replaces the raw request body before a controller sees it.
 *
 * Idempotency lives on two layers, matching T-07's own MarkInstallmentPaid
 * shape: stripe_events.stripe_event_id (unique) is what a redelivery is
 * actually checked against — a duplicate's INSERT fails, which this action
 * treats as "already seen", not an error — and MarkInstallmentPaid's own
 * paid-guard is the second line, for the theoretical case of two different
 * event IDs describing the same settlement.
 *
 * Acknowledges before processing: everything past signature verification
 * and the stripe_events write is a queued App\Payments\Jobs\
 * ProcessStripeWebhookEvent, dispatched and never awaited, so a slow queue
 * can never be why Stripe thinks a delivery failed and retries it.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $event = $this->verify($request);

        if ($event === null) {
            return response('', Response::HTTP_BAD_REQUEST);
        }

        if (! $this->recordOnce($event)) {
            // Already seen — Stripe's own redelivery, or a race with one.
            // Still 2xx: the whole point is that this is not a failure.
            return response('', Response::HTTP_NO_CONTENT);
        }

        ProcessStripeWebhookEvent::dispatch($event->toArray())->onQueue('default');

        return response('', Response::HTTP_NO_CONTENT);
    }

    private function verify(Request $request): ?Event
    {
        $secret = (string) config('services.stripe.webhook_secret');

        // Ein leerer Schlüssel ist ein gültiger HMAC-Schlüssel: ohne Secret
        // wäre jede Signatur fälschbar.
        if ($secret === '') {
            return null;
        }

        try {
            return Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }
    }

    /** True the first time this event ID is seen; false for a duplicate. */
    private function recordOnce(Event $event): bool
    {
        try {
            // DB::transaction(), not a bare create(): Postgres marks a whole
            // transaction aborted the moment any statement inside it fails,
            // catch or no catch, so a duplicate key error here would poison
            // every query for the rest of the request (and, under
            // RefreshDatabase's own per-test transaction, the rest of the
            // test) unless it runs inside its own SAVEPOINT. Laravel's
            // transaction() issues exactly that savepoint automatically
            // whenever it is called while already inside a transaction.
            DB::transaction(function () use ($event) {
                StripeEvent::query()->create([
                    'stripe_event_id' => $event->id,
                    'type' => $event->type,
                ]);
            });

            return true;
        } catch (QueryException $e) {
            // 23505: Postgres's unique_violation SQLSTATE — the only failure
            // mode a duplicate stripe_event_id can produce here, since every
            // other column is a well-formed value straight off a Stripe SDK
            // object. Anything else (a real connectivity or schema problem)
            // is left to propagate rather than silently treated as "already
            // seen", which would drop a genuinely new event on the floor.
            if ($e->getCode() === '23505') {
                return false;
            }

            throw $e;
        }
    }
}
