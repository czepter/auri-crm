<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Stripe's own testing seam (\Stripe\ApiRequestor::setHttpClient()) rather
 * than a second one this repo would have to invent: stripe-php never goes
 * through Laravel's Http facade, so Http::fake() cannot see these calls, but
 * the SDK itself is built to swap its HTTP layer out for exactly this.
 *
 * Responses are consumed in the order given — one array per expected call —
 * so a test lists what Stripe should say to each request the action under
 * test is expected to make, and nothing more. $requests then lets a test
 * assert how many calls actually happened and what was sent, which is the
 * whole of M-04's idempotency assertion: create once, reuse after.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: array, params: array}> */
    public array $requests = [];

    /** @param list<array{status: int, body: array}> $responses */
    public function __construct(private array $responses) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'headers' => $headers, 'params' => $params];

        $response = array_shift($this->responses);

        if ($response === null) {
            throw new \RuntimeException('FakeStripeHttpClient: no more canned responses for '.$method.' '.$absUrl);
        }

        return [json_encode($response['body']), $response['status'], []];
    }
}
