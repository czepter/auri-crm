<?php

namespace App\Http\Middleware;

use App\Integration\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * I-03: every write route on the api surface carries this, after `scope:` —
 * the caller is a workflow runner with a retry policy, and a retried
 * "capture lead" must not produce two leads, nor may the retry see a
 * different answer than the original call got.
 *
 * The idempotency row is claimed FIRST, by a plain insert that
 * idempotency_keys' own unique constraint on (tenant_id, principal_type,
 * principal_id, idempotency_key) guards — the route only ever runs once
 * that claim succeeds, so two overlapping callers racing the same key can
 * only ever produce one write, never two. The principal is whichever kind
 * of bearer authenticated the call — an ApiToken (I-01) or, since N-04, a
 * staff User authenticated by their own OAuth token — so two different
 * principals reusing the same key never collide with each other (see
 * ApiIdempotencyTest's "scopes idempotency keys per token" case). See the
 * migration's own docblock, and App\Automation\Actions\FireAutomationRule
 * for the same pattern used for a fire-and-forget send rather than an HTTP
 * response that has to be replayed verbatim — this middleware additionally
 * caches the response body, which is the part a replay actually needs back.
 */
class EnsureIdempotentWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (blank($key)) {
            return response()->json(['message' => 'The Idempotency-Key header is required.'], 400);
        }

        /** @var Model $principal */
        $principal = $request->user('api');
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $scope = fn () => IdempotencyKey::query()
            ->where('principal_type', $principal::class)
            ->where('principal_id', $principal->getKey())
            ->where('idempotency_key', $key);

        $existing = (clone $scope())
            ->where('created_at', '>=', now()->subHours(IdempotencyKey::WINDOW_HOURS))
            ->first();

        if ($existing !== null) {
            return $this->respondFrom($existing, $fingerprint);
        }

        // A claim outside the window no longer replays, but its unique
        // index entry still blocks the insert below until Prunable's daily
        // sweep reaches it — drop it here so the retry redoes the write now.
        (clone $scope())
            ->where('created_at', '<', now()->subHours(IdempotencyKey::WINDOW_HOURS))
            ->delete();

        try {
            $claim = IdempotencyKey::create([
                'principal_type' => $principal::class,
                'principal_id' => $principal->getKey(),
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race: another caller claimed this key first, between
            // the lookup above and this insert.
            $existing = IdempotencyKey::query()
                ->where('principal_type', $principal::class)
                ->where('principal_id', $principal->getKey())
                ->where('idempotency_key', $key)
                ->first();

            return $existing !== null
                ? $this->respondFrom($existing, $fingerprint)
                : response()->json(['message' => 'A request with this idempotency key is already in progress.'], 409);
        }

        // Laravel's routing pipeline (Illuminate\Routing\Pipeline, unlike the
        // plain one) already renders a thrown exception — a validation
        // failure, a DomainException LeadController turns into one — into a
        // Response before it reaches back up through $next() here, so a
        // status check is the only hook this middleware gets; nothing ever
        // throws past this call.
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            // The write never happened — nothing worth replaying, and
            // leaving the claim behind would 409 every retry forever, even
            // one sent under the same key with a corrected payload.
            $claim->delete();

            return $response;
        }

        $claim->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
        ]);

        return $response;
    }

    private function respondFrom(IdempotencyKey $existing, string $fingerprint): Response
    {
        if ($existing->request_fingerprint !== $fingerprint) {
            return response()->json([
                'message' => 'This Idempotency-Key was already used with a different request.',
            ], 422);
        }

        if ($existing->response_status === null) {
            // Claimed but the original call hasn't finished yet.
            return response()->json(['message' => 'A request with this idempotency key is already in progress.'], 409);
        }

        return response()->json($existing->response_body ?? [], $existing->response_status);
    }
}
