<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\Project;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Der Projekttyp-Filter der Auswertungen, als Unterabfrage statt als Join.
 *
 * Jede Kennzahl hängt an einer anderen Tabelle — leads, jobs, shoots,
 * audit_logs, quotes — und alle tragen project_id. Ein Join müsste in jeder
 * Abfrage anders heißen und würde die Gruppierungen verschieben; ein
 * `where in (select id …)` lässt die Abfrage, wie sie ist.
 */
class ProjectFilter
{
    /** Ohne Typ bleibt die Abfrage unangetastet. */
    public static function apply(EloquentBuilder|Builder $query, ?ProjectType $type, string $column = 'project_id'): void
    {
        if ($type === null) {
            return;
        }

        $query->whereIn($column, Project::query()->select('id')->where('type', $type->value));
    }
}
