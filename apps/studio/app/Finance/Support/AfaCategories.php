<?php

namespace App\Finance\Support;

/**
 * Useful lives the studio may pick instead of typing months. Every entry needs
 * a cited AfA-Tabelle row; anything else is a typed month count.
 */
final class AfaCategories
{
    /** BMF-Schreiben 2021 and 2022: computer hardware and software, one year. */
    private const MONTHS = ['computer_software' => 12];

    private const LABELS = ['computer_software' => 'Computer, Software'];

    public static function months(?string $key): ?int
    {
        return self::MONTHS[$key] ?? null;
    }

    /** @return list<array{key: string, label: string, months: int}> */
    public static function options(): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'label' => self::LABELS[$key], 'months' => self::MONTHS[$key]],
            array_keys(self::MONTHS),
        );
    }
}
