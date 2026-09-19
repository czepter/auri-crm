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

        $counted = $query->get()->keyBy('month');

        return collect(MonthRange::keys($from, $to))
            ->map(fn (string $month) => ['month' => $month, 'shoots' => (int) ($counted[$month]->shoots ?? 0)])
            ->all();
    }

    /**
     * Vorlaufzeit gebuchter Aufträge: Erfassung bis Haupttermin.
     *
     * Die Stufen richten sich nach den Zeiten selbst. Ein Studio, das zwei
     * Wochen vorher gebucht wird, liest in Monatsstufen nur eine volle erste
     * Stufe und vier leere; dasselbe gilt für Hochzeiten in Tagesstufen. Die
     * Einheit, in der die Mehrzahl der Vorläufe liegt, gibt die Stufen vor.
     *
     * Nur der Haupttermin zählt. Ein Kennenlern-Shooting und die Trauung sind
     * derselbe Auftrag, und beide zu zählen macht aus einer Hochzeit zwei
     * Vorlaufzeiten. Aufträge ohne Termin stehen als eigene Zahl daneben,
     * nicht in der letzten Stufe.
     *
     * @return array{buckets: list<array{key: string, label: string, projects: int}>, median: string|null, withoutDate: int}
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

        $days = $rows
            ->filter(fn ($row) => $row->date !== null)
            ->map(fn ($row) => max(0.0, $row->captured_at->floatDiffInDays($row->date)))
            ->values();

        $scale = self::scaleFor($days);
        $keys = ['q1', 'q2', 'q3', 'q4', 'later'];

        $buckets = [];

        foreach ($scale['steps'] as $index => $step) {
            $min = $index === 0 ? null : $scale['steps'][$index - 1]['edge'];

            $buckets[] = [
                'key' => $keys[$index],
                'label' => $step['label'],
                'projects' => $days->filter(fn (float $value) => ($min === null || $value > $min)
                    && ($step['edge'] === null || $value <= $step['edge']))->count(),
            ];
        }

        return [
            'buckets' => $buckets,
            'median' => $days->isEmpty()
                ? null
                : ($scale['median'])($days->sort()->values()[intdiv($days->count(), 2)]),
            'withoutDate' => $rows->count() - $days->count(),
        ];
    }

    private const DAYS_PER_MONTH = 30.4375;

    /**
     * Stufen und Median-Beschriftung in der Einheit, in der die Mehrzahl der
     * Vorläufe liegt. Bei Gleichstand gewinnt die gröbere: zwei Termine in
     * zwei Wochen und zwei in einem halben Jahr sind eine Wochenachse, auf
     * der die Hälfte in der letzten Stufe klebt.
     *
     * @param  \Illuminate\Support\Collection<int, float>  $days
     * @return array{steps: list<array{label: string, edge: float|null}>, median: callable(float): string}
     */
    private static function scaleFor($days): array
    {
        $week = 7.0;
        $month = self::DAYS_PER_MONTH;

        $inDays = $days->filter(fn (float $value) => $value < 2 * $week)->count();
        $inWeeks = $days->filter(fn (float $value) => $value >= 2 * $week && $value < 6 * $month)->count();
        $inMonths = $days->count() - $inDays - $inWeeks;

        if ($inDays > $inWeeks && $inDays > $inMonths) {
            return [
                'steps' => [
                    ['label' => 'unter 2 Tage', 'edge' => 2.0],
                    ['label' => '2–7 Tage', 'edge' => 7.0],
                    ['label' => '1–2 Wochen', 'edge' => 14.0],
                    ['label' => '2–4 Wochen', 'edge' => 28.0],
                    ['label' => 'über 4 Wochen', 'edge' => null],
                ],
                'median' => fn (float $value) => round($value) == 1 ? '1 Tag' : round($value).' Tage',
            ];
        }

        if ($inWeeks > $inMonths) {
            return [
                'steps' => [
                    ['label' => 'unter 2 Wochen', 'edge' => 2 * $week],
                    ['label' => '2–4 Wochen', 'edge' => 4 * $week],
                    ['label' => '4–8 Wochen', 'edge' => 8 * $week],
                    ['label' => '8–12 Wochen', 'edge' => 12 * $week],
                    ['label' => 'über 12 Wochen', 'edge' => null],
                ],
                'median' => fn (float $value) => round($value / $week) == 1 ? '1 Woche' : round($value / $week).' Wochen',
            ];
        }

        return [
            'steps' => [
                ['label' => 'unter 3 Mon.', 'edge' => 3 * $month],
                ['label' => '3–6 Mon.', 'edge' => 6 * $month],
                ['label' => '6–9 Mon.', 'edge' => 9 * $month],
                ['label' => '9–12 Mon.', 'edge' => 12 * $month],
                ['label' => 'über 12 Mon.', 'edge' => null],
            ],
            'median' => fn (float $value) => number_format($value / $month, 1, ',', '').' Mon.',
        ];
    }
}
