<?php

namespace App\Support\Observability;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\Integration\RequestIntegration;

/**
 * F-11's `before_send` hook (config/sentry.php). `send_default_pii => false`
 * already keeps Sentry's own RequestIntegration from attaching cookies, an
 * IP address, or unredacted Authorization/Cookie/Proxy-Authorization/
 * Set-Cookie headers — this covers what that doesn't: fields inside the
 * request body, a signed URL's signature, and anything email-shaped,
 * wherever an event happens to carry them.
 *
 * Proven against a constructed Event in
 * tests/Feature/Observability/ScrubSensitiveDataTest.php, per F-11's
 * acceptance criterion — an assertion-free implementation was explicitly
 * ruled insufficient.
 */
class ScrubSensitiveData
{
    private const REDACTED = '[Filtered]';

    /** Field names redacted wherever they appear in request data or extra context. */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'totp', 'totp_code', 'two_factor_code', 'one_time_password', 'otp',
        'recovery_code', 'recovery_codes',
    ];

    /** @see RequestIntegration::DEFAULT_SENSITIVE_HEADERS */
    private const SENSITIVE_HEADERS = ['authorization', 'cookie', 'proxy-authorization', 'set-cookie'];

    public function __invoke(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if ($request !== []) {
            $event->setRequest($this->scrubRequest($request));
        }

        $event->setExtra($this->scrubFields($event->getExtra()));

        $user = $event->getUser();

        if ($user !== null && $user->getEmail() !== null) {
            $user->setEmail(self::REDACTED);
        }

        return $event;
    }

    private function scrubRequest(array $request): array
    {
        if (isset($request['headers']) && is_array($request['headers'])) {
            $request['headers'] = $this->scrubHeaders($request['headers']);
        }

        if (isset($request['cookies'])) {
            $request['cookies'] = self::REDACTED;
        }

        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = $this->scrubSignature($request['url']);
        }

        if (isset($request['query_string']) && is_string($request['query_string'])) {
            $request['query_string'] = $this->scrubSignature($request['query_string']);
        }

        if (isset($request['data']) && is_array($request['data'])) {
            $request['data'] = $this->scrubFields($request['data']);
        }

        return $request;
    }

    private function scrubHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_HEADERS, true)) {
                $headers[$name] = self::REDACTED;
            }
        }

        return $headers;
    }

    /** Laravel signed URLs (URL::signedRoute/temporarySignedRoute) carry the proof in `signature`. */
    private function scrubSignature(string $value): string
    {
        return preg_replace('/([?&])(signature|expires)=[^&]*/i', '$1$2='.self::REDACTED, $value) ?? $value;
    }

    private function scrubFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->scrubFields($value);

                continue;
            }

            $name = strtolower((string) $key);

            if (in_array($name, self::SENSITIVE_FIELDS, true)
                || str_contains($name, 'email')
                || (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL))) {
                $data[$key] = self::REDACTED;
            }
        }

        return $data;
    }
}
