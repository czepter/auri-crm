<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Der Projekttyp-Filter der Auswertungen, als Unterabfrage statt als Join.
 *
 * Jede Kennzahl hängt an einer anderen Tabelle — leads, payment_plans, shoots,
 * audit_logs, quotes — und alle tragen case_id. Ein Join müsste in jeder
 * Abfrage anders heißen und würde die Gruppierungen verschieben; ein
 * `where in (select id …)` lässt die Abfrage, wie sie ist.
 */
class ProjectFilter
{
    /** Ohne Typ bleibt die Abfrage unangetastet. */
    public static function apply(EloquentBuilder|Builder $query, ?ProjectType $type, string $column = 'case_id'): void
    {
        if ($type === null) {
            return;
        }

        $query->whereIn($column, ClientCase::query()->select('id')->where('type', $type->value));
    }
}
