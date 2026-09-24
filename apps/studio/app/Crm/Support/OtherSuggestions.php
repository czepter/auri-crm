<?php

namespace App\Crm\Support;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;

/**
 * What the studio has already typed under "Sonstige", most used first — the
 * suggestions the three free-text fields at capture offer. Free text stays
 * free text; this only spares retyping it.
 */
class OtherSuggestions
{
    private const LIMIT = 20;

    /** @return list<string> */
    public static function for(string $kind): array
    {
        [$model, $column] = match ($kind) {
            'type' => [ClientCase::class, 'type_other'],
            'source' => [Lead::class, 'source_other'],
            'channel' => [Lead::class, 'captured_via_other'],
        };

        return $model::query()
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderByRaw('count(*) desc')
            ->orderBy($column)
            ->limit(self::LIMIT)
            ->pluck($column)
            ->all();
    }
}
