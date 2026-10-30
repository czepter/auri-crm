<?php

namespace App\Privacy\Data;

use App\Models\StudioSettings;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Was der Lauf aus einer abgelaufenen Anfrage entfernt. `true` heißt weg:
 * beim Namen anonymisiert, sonst gelöscht. Der Auftrag trägt die anderen
 * Teile, mit ihm gehen sie alle.
 */
#[TypeScript]
class RetentionPolicyData extends Data
{
    private const PARTS = ['name', 'text', 'messages', 'offers', 'galleries', 'order'];

    public function __construct(
        public bool $name,
        public bool $text,
        public bool $messages,
        public bool $offers,
        public bool $galleries,
        public bool $order,
    ) {
        if ($order) {
            $this->name = $this->text = $this->messages = $this->offers = $this->galleries = true;
        }
    }

    /** Person und Inhalte weg, der Auftrag bleibt für die Auswertung. */
    public static function anonymize(): self
    {
        return new self(true, true, true, true, true, false);
    }

    public static function everything(): self
    {
        return new self(true, true, true, true, true, true);
    }

    /** @param  list<string>|null  $stored */
    /** Was der Lauf mit einer abgelaufenen Anfrage tut; ohne Wahl wird anonymisiert. */
    public static function current(): self
    {
        return self::fromStored(StudioSettings::current()->retention_policy);
    }

    public static function fromStored(?array $stored): self
    {
        if ($stored === null) {
            return self::anonymize();
        }

        return new self(...array_combine(self::PARTS, array_map(fn (string $part) => in_array($part, $stored, true), self::PARTS)));
    }

    /** @return list<string> */
    public function removed(): array
    {
        return array_keys(array_filter($this->toArray()));
    }
}
