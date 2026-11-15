<?php

namespace App\Integration\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Every tool this server registers. What varies between them is a
 * ToolDefinition; what they all do is here.
 */
class ApiTool extends Tool
{
    /**
     * The backfill switch, on every write tool because a migration touches
     * every one of them. What it suppresses is decided by the api surface
     * (App\Http\Middleware\MarkSilentWrite); the header is the whole
     * contract this side holds.
     */
    private const SILENT_DESCRIPTION = 'For entering history that already happened: no mail, no in-app notice, and no follow-up tasks. Use it for every write of a record from before this studio moved onto the app.';

    public function __construct(private readonly ToolDefinition $definition) {}

    public function name(): string
    {
        return $this->definition->name;
    }

    public function title(): string
    {
        return $this->definition->title;
    }

    public function description(): string
    {
        return $this->definition->description;
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return $this->definition->annotations;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = ($this->definition->schema)($schema);

        if ($this->definition->silent !== null && ! array_key_exists('silent', $properties)) {
            $properties['silent'] = $schema->boolean()->description(self::SILENT_DESCRIPTION);
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->definition->outputSchema === null ? [] : ($this->definition->outputSchema)($schema);
    }

    public function handle(Request $request, ApiCall $call): Response|ResponseFactory
    {
        $arguments = $request->all();
        $silent = $arguments['silent'] ?? $this->definition->silent;
        unset($arguments['silent']);

        $path = $this->definition->path;
        $payload = $arguments;

        foreach ($arguments as $key => $value) {
            if (str_contains($path, '{'.$key.'}')) {
                $path = str_replace('{'.$key.'}', rawurlencode((string) $value), $path);
                unset($payload[$key]);
            }
        }

        if ($this->definition->payload !== null) {
            $payload = ($this->definition->payload)($arguments);
        }

        $response = $call(
            $this->definition->method,
            $path,
            $payload,
            $this->definition->method === 'GET'
                ? []
                : ['Idempotency-Key' => $this->idempotencyKey($arguments, $silent === true)]
                    + ($silent ? ['X-Silent-Write' => '1'] : []),
        );

        return $this->result($response, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function result(HttpResponse $response, array $arguments): Response|ResponseFactory
    {
        $body = (string) $response->getContent();

        if (! $response->isSuccessful()) {
            $status = $response->getStatusCode();

            return Response::error($status.' '.(HttpResponse::$statusTexts[$status] ?? '').': '.$this->errorMessage($body));
        }

        if ($this->definition->confirmation !== null) {
            return Response::text(($this->definition->confirmation)($arguments));
        }

        if ($this->definition->structure === null) {
            return Response::text($body);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $this->keepEmptyObjects(json_decode($body)) ?? [];

        return Response::structured(($this->definition->structure)($decoded));
    }

    /** An assoc decode cannot tell `{}` from `[]`, so empty objects are kept as stdClass. */
    private function keepEmptyObjects(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value) === []
                ? $value
                : array_map($this->keepEmptyObjects(...), get_object_vars($value));
        }

        return is_array($value) ? array_map($this->keepEmptyObjects(...), $value) : $value;
    }

    private function errorMessage(string $body): string
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! is_string($decoded['message'] ?? null)) {
            return 'The api surface returned an error with no message.';
        }

        return isset($decoded['errors'])
            ? $decoded['message'].' '.json_encode($decoded['errors'])
            : $decoded['message'];
    }

    /**
     * EnsureIdempotentWrite keys a claim on (principal, key), so the key has
     * to be stable across a retry of the *same* logical write and different
     * for a different one. It is built out of the arguments rather than the
     * MCP request id: claude.ai reuses one id for every call of a session,
     * so an id-derived key refused the second write of a session as a replay
     * of the first. A retry sends the same arguments; two different writes
     * differ in at least one.
     *
     * ponytail: two writes that are byte-identical on purpose (the same note
     * filed twice) collide inside the claim's window and the second is
     * refused. Give the tools a caller-supplied request id if that ever
     * needs to be possible.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function idempotencyKey(array $arguments, bool $silent): string
    {
        // `silent` decides whether mail and follow-up tasks fire, so it is
        // part of what makes two writes the same write.
        $digest = hash('sha256', $this->canonical($arguments + ['silent' => $silent]));

        return 'mcp:'.$this->definition->name.':'.substr($digest, 0, 32);
    }

    /** JSON with object keys in a fixed order, so argument order cannot change the key. */
    private function canonical(mixed $value): string
    {
        if (! is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (array_is_list($value)) {
            return '['.implode(',', array_map($this->canonical(...), $value)).']';
        }

        ksort($value);

        $entries = [];

        foreach ($value as $key => $item) {
            $entries[] = json_encode((string) $key, JSON_THROW_ON_ERROR).':'.$this->canonical($item);
        }

        return '{'.implode(',', $entries).'}';
    }
}
