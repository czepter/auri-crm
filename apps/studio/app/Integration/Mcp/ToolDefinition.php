<?php

namespace App\Integration\Mcp;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * One MCP tool as a value: what the assistant is told about it, and which
 * route in routes/api.php it stands for. Tools are data rather than a class
 * apiece because every one of them is the same tool — read the arguments,
 * call one api route, hand the answer back — and a hundred classes differing
 * only in their strings hide that.
 */
final class ToolDefinition
{
    public const READ = ['readOnlyHint' => true];

    /** A write that only ever adds. */
    public const ADDS = ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false];

    /** A write that overwrites or removes existing state. */
    public const OVERWRITES = ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false];

    /**
     * @param  Closure(JsonSchema): array<string, mixed>  $schema
     * @param  array<string, bool>  $annotations
     * @param  bool|null  $silent  What the backfill switch stands at when the caller leaves it out, or null for a tool that does not carry one.
     * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $payload  Turns the arguments into what the route is called with, where the two differ.
     * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $structure  Reshapes the api payload into the tool's own result object.
     * @param  (Closure(array<string, mixed>): string)|null  $confirmation  What to answer where the route answers 204 and there is no body to hand back.
     * @param  (Closure(JsonSchema): array<string, mixed>)|null  $outputSchema
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public string $method,
        public string $path,
        public Closure $schema,
        public array $annotations = self::ADDS,
        public ?bool $silent = null,
        public ?Closure $payload = null,
        public ?Closure $structure = null,
        public ?Closure $outputSchema = null,
        public ?Closure $confirmation = null,
    ) {}
}
