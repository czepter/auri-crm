<?php

namespace App\Http\Middleware;

use App\Integration\Models\ApiToken;
use App\Integration\Models\IdempotencyKey;
use Closure;
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
 * idempotency_keys' own unique constraint on (tenant_id, api_token_id,
 * idempotency_key) guards — the route only ever runs once that claim
 * succeeds, so two overlapping callers racing the same key can only ever
 * produce one write, never two. See the migration's own docblock, and
 * App\Automation\Actions\FireAutomationRule for the same pattern used for a
 * fire-and-forget send rather than an HTTP response that has to be replayed
 * verbatim — this middleware additionally caches the response body, which is
 * the part a replay actually needs back.
 */
class EnsureIdempotentWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (blank($key)) {
            return response()->json(['message' => 'The Idempotency-Key header is required.'], 400);
        }

        /** @var ApiToken $token */
        $token = $request->user('api');
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $existing = IdempotencyKey::query()
            ->where('api_token_id', $token->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing !== null) {
            return $this->respondFrom($existing, $fingerprint);
        }

        try {
            $claim = IdempotencyKey::create([
                'api_token_id' => $token->id,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race: another caller claimed this key first, between
            // the lookup above and this insert.
            $existing = IdempotencyKey::query()
                ->where('api_token_id', $token->id)
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
