<?php

namespace App\Engagement\Http\Controllers;

use App\Engagement\Actions\IngestInboundMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * E-12's one door: the inbound provider posts a delivery here.
 *
 * The signature is verified **before the body is parsed**. A webhook endpoint
 * that parses first and authenticates second has already run a stranger's
 * input through its own parser, which is the shape most webhook bugs take.
 *
 * There is no mailbox connection behind this, and that is the whole design:
 * the app's own hosting owns the inbound domain and the provider hands us
 * what arrives on it. The studio never gives up an IMAP password or an OAuth
 * mailbox grant, so there is nothing here to leak and nothing to re-authorise
 * when their provider rotates a credential.
 *
 * Always 202, whatever the routing decision was. The provider's job is to
 * know we accepted the delivery; which tray it landed in is the studio's
 * business, and reporting it would only teach a sender which addresses
 * thread.
 */
class InboundMailController extends Controller
{
    public function __invoke(Request $request, IngestInboundMessage $ingest): JsonResponse
    {
        abort_unless($this->signatureValid($request), 401);

        $validated = $request->validate([
            'message_id' => ['nullable', 'string', 'max:998'],
            'from' => ['required', 'email', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:998'],
            'body' => ['required', 'string', 'max:'.config('mail.inbound.max_bytes')],
            'spf' => ['nullable', 'string', 'max:32'],
            'dkim' => ['nullable', 'string', 'max:32'],
            'dmarc' => ['nullable', 'string', 'max:32'],
            'spam_score' => ['nullable', 'integer'],
        ]);

        $ingest($validated);

        return response()->json(['status' => 'received'], 202);
    }

    /**
     * HMAC over the raw body, compared in constant time. An endpoint with no
     * configured secret refuses everything rather than accepting everything:
     * a deployment that has not provisioned inbound mail has no business
     * ingesting any.
     */
    private function signatureValid(Request $request): bool
    {
        $secret = config('mail.inbound.signing_secret');

        if (blank($secret)) {
            return false;
        }

        $provided = (string) $request->header('X-Inbound-Signature');

        return $provided !== '' && hash_equals(
            hash_hmac('sha256', $request->getContent(), (string) $secret),
            $provided,
        );
    }
}
