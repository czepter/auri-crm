<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use Carbon\CarbonInterface;

/**
 * Die Termine selbst: wann gearbeitet wird, und wie weit sie der Anfrage
 * vorausliegen.
 *
 * Auslastung fenstert über das Termindatum, nicht über die Erfassung — die
 * Frage ist "wann stehe ich im Feld", und die beantwortet der Kalender.
 * Die Vorlaufzeit dagegen gehört zur Anfrage und fenstert mit ihr.
 */
class ShootReport
{
    /**
     * Termine je Monat im Fenster, ältester zuerst.
     *
     * @return list<array{month: string, shoots: int}>
     */
    public static function byMonth(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $query = Shoot::query()
            ->selectRaw("to_char(date_trunc('month', shoots.date), 'YYYY-MM') as month")
            ->selectRaw('count(*) as shoots')
            ->whereNotNull('shoots.date')
            ->whereBetween('shoots.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('month')
            ->orderBy('month');

        ProjectFilter::apply($query, $type, 'shoots.project_id');

        return $query->get()
            ->map(fn ($row) => ['month' => $row->month, 'shoots' => (int) $row->shoots])
            ->all();
    }

    /**
     * Vorlaufzeit gebuchter Aufträge: Erfassung bis Haupttermin, in Stufen
     * von drei Monaten.
     *
     * Nur der Haupttermin zählt. Ein Kennenlern-Shooting und die Trauung sind
     * derselbe Auftrag, und beide zu zählen macht aus einer Hochzeit zwei
     * Vorlaufzeiten. Aufträge ohne Termin stehen als eigene Zahl daneben,
     * nicht in der letzten Stufe.
     *
     * @return array{buckets: list<array{key: string, label: string, projects: int}>, medianMonths: float|null, withoutDate: int}
     */
    public static function leadTimes(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $query = Lead::query()
            ->join('jobs', 'jobs.project_id', '=', 'leads.project_id')
            ->join('shoots', function ($join) {
                $join->on('shoots.project_id', '=', 'leads.project_id')->where('shoots.is_primary', true);
            })
            ->whereBetween('leads.captured_at', [$from, $to])
            ->selectRaw('shoots.date, leads.captured_at');

        ProjectFilter::apply($query, $type, 'leads.project_id');

        $rows = $query->get();

        $months = $rows
            ->filter(fn ($row) => $row->date !== null)
            ->map(fn ($row) => max(0.0, $row->captured_at->floatDiffInMonths($row->date)))
            ->values();

        $ranges = [
            ['key' => 'q1', 'label' => 'unter 3 Mon.', 'min' => null, 'max' => 3],
            ['key' => 'q2', 'label' => '3–6 Mon.', 'min' => 3, 'max' => 6],
            ['key' => 'q3', 'label' => '6–9 Mon.', 'min' => 6, 'max' => 9],
            ['key' => 'q4', 'label' => '9–12 Mon.', 'min' => 9, 'max' => 12],
            ['key' => 'later', 'label' => 'über 12 Mon.', 'min' => 12, 'max' => null],
        ];

        $buckets = array_map(fn (array $range) => [
            'key' => $range['key'],
            'label' => $range['label'],
            'projects' => $months->filter(fn (float $value) => ($range['min'] === null || $value > $range['min'])
                && ($range['max'] === null || $value <= $range['max']))->count(),
        ], $ranges);

        return [
            'buckets' => $buckets,
            'medianMonths' => $months->isEmpty()
                ? null
                : round($months->sort()->values()[intdiv($months->count(), 2)], 1),
            'withoutDate' => $rows->count() - $months->count(),
        ];
    }
}
