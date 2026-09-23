<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\Stage;
use App\Models\AuditLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Was der Trichter zwischen den Schritten verliert, und wie lange er dafür
 * braucht.
 *
 * Quelle ist der Audit-Trail, nicht die Anfrage-Zeile: `leads.stage` und
 * `leads.stage_entered_at` kennen nur den Schritt, in dem eine Anfrage jetzt
 * steht. Jeder Wechsel liegt seit P-01 als `lead.stage_changed` mit `from`
 * und `to` im Trail — dieselbe Quelle, aus der StageHistory eine Rücknahme
 * liest.
 *
 * Alles fenstert über `leads.captured_at`, wie BookingFunnel: eine im
 * November erfasste und im Januar gebuchte Anfrage gehört auf beiden Seiten
 * zum November.
 *
 * ponytail: drei Aggregationen über audit_logs je Seitenaufruf. Ein
 * Teilindex auf (event, subject) trägt das, solange der Trail nicht
 * sechsstellig wird; darüber gehört das Ergebnis in eine Tagescache-Zeile.
 */
class FunnelTimes
{
    /**
     * Wie viele Anfragen jeden Schritt mindestens erreicht haben, in der
     * Reihenfolge des Trichters.
     *
     * Gezählt wird "so weit oder weiter": wer am Telefon zusagt, springt von
     * Neu auf Gebucht, und ein Schritt, der dabei leer bleibt, würde den
     * Trichter unter seinem eigenen Ergebnis ausweisen.
     *
     * @return list<array{key: string, label: string, leads: int, lost: int, drop: int|null}>
     */
    public static function steps(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $stages = Stage::ordered(StagePipeline::Lead);

        if ($stages->isEmpty()) {
            return [];
        }

        $captured = self::capturedLeads($from, $to, $type)->count();

        if ($captured === 0) {
            return [];
        }

        $order = $stages->pluck('sort_order', 'key');

        $reached = self::moves($from, $to, $type)
            ->selectRaw('audit_logs.subject, max(stages.sort_order) as furthest')
            ->join('stages', function ($join) {
                $join->on('stages.key', '=', DB::raw("audit_logs.context->>'to'"))
                    ->where('stages.pipeline', StagePipeline::Lead->value);
            })
            ->groupBy('audit_logs.subject')
            ->pluck('furthest');

        $steps = [];
        $before = null;

        foreach ($stages as $stage) {
            // Der Eingangsschritt ist jede erfasste Anfrage: er hat keinen
            // Wechsel, der ihn in den Trail schreibt.
            $leads = $stage->anchor === StageAnchor::Entry
                ? $captured
                : $reached->filter(fn ($furthest) => (int) $furthest >= (int) $order[$stage->key])->count();

            $steps[] = [
                'key' => $stage->key,
                'label' => $stage->name,
                'leads' => $leads,
                'lost' => $before === null ? 0 : max(0, $before - $leads),
                'drop' => $before === null || $before === 0
                    ? null
                    : (int) round(max(0, $before - $leads) / $before * 100),
            ];

            $before = $leads;
        }

        return $steps;
    }

    /**
     * Median der Verweildauer je Schritt, in Tagen.
     *
     * Die Dauer in einem Schritt ist der Abstand zum vorigen Wechsel derselben
     * Anfrage — für den ersten Wechsel der Abstand zur Erfassung. Median statt
     * Mittelwert: eine Anfrage, die zwei Jahre lag, ist kein Durchschnitt.
     *
     * @return list<array{key: string, label: string, days: float, moves: int}>
     */
    public static function dwell(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $inner = self::moves($from, $to, $type)
            ->selectRaw("audit_logs.context->>'from' as stage")
            ->selectRaw('extract(epoch from (audit_logs.created_at - coalesce(
                lag(audit_logs.created_at) over (partition by audit_logs.subject order by audit_logs.created_at),
                leads.captured_at
            ))) as seconds')
            ->toBase();

        $rows = DB::query()
            ->fromSub($inner, 'moves')
            ->whereNotNull('stage')
            ->groupBy('stage')
            ->selectRaw('stage, percentile_cont(0.5) within group (order by seconds) as median, count(*) as moves')
            ->get();

        $names = Stage::ordered(StagePipeline::Lead);

        return $names
            ->map(function (Stage $stage) use ($rows) {
                $row = $rows->firstWhere('stage', $stage->key);

                return $row === null ? null : [
                    'key' => $stage->key,
                    'label' => $stage->name,
                    'days' => round(((float) $row->median) / 86400, 1),
                    'moves' => (int) $row->moves,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Wie lange bis zur ersten Antwort — Erfassung bis zum ersten
     * Schritt-Wechsel, in vier Stufen.
     *
     * Anfragen ohne jeden Wechsel stehen als eigene Zahl daneben, nicht in der
     * letzten Stufe: unbeantwortet ist kein langsam beantwortet.
     *
     * @return array{buckets: list<array{key: string, label: string, leads: int}>, medianHours: float|null, unanswered: int}
     */
    public static function responseTimes(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $first = self::moves($from, $to, $type)
            ->selectRaw('extract(epoch from (min(audit_logs.created_at) - leads.captured_at)) as seconds')
            ->groupBy('audit_logs.subject', 'leads.captured_at')
            ->toBase();

        $seconds = DB::query()->fromSub($first, 'first_move')->pluck('seconds')
            ->map(fn ($value) => max(0.0, (float) $value));

        $answered = $seconds->count();
        $captured = self::capturedLeads($from, $to, $type)->count();

        $ranges = [
            ['key' => 'day', 'label' => 'unter 24 Std.', 'min' => null, 'max' => 86400],
            ['key' => 'days3', 'label' => '1–3 Tage', 'min' => 86400, 'max' => 3 * 86400],
            ['key' => 'days7', 'label' => '3–7 Tage', 'min' => 3 * 86400, 'max' => 7 * 86400],
            ['key' => 'later', 'label' => 'über 7 Tage', 'min' => 7 * 86400, 'max' => null],
        ];

        $counted = array_map(fn (array $range) => [
            'key' => $range['key'],
            'label' => $range['label'],
            'leads' => $seconds->filter(fn (float $value) => ($range['min'] === null || $value > $range['min'])
                && ($range['max'] === null || $value <= $range['max']))->count(),
        ], $ranges);

        return [
            'buckets' => $counted,
            'medianHours' => $answered === 0 ? null : round($seconds->sort()->values()[intdiv($answered, 2)] / 3600, 1),
            'unanswered' => max(0, $captured - $answered),
        ];
    }

    /** Anfragen, die im Fenster erfasst wurden. */
    private static function capturedLeads(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type)
    {
        $query = Lead::query()->whereBetween('leads.captured_at', [$from, $to]);

        ProjectFilter::apply($query, $type, 'leads.case_id');

        return $query;
    }

    /**
     * Jeder Schritt-Wechsel einer im Fenster erfassten Anfrage.
     *
     * Der Join geht über public_id UND tenant_id: public_ids sind je Mandant
     * vergeben, und ein Join allein darüber könnte zwei Mandanten verschränken.
     */
    private static function moves(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type)
    {
        $query = AuditLog::query()
            ->join('leads', function ($join) {
                $join->on('leads.public_id', '=', 'audit_logs.subject')
                    ->on('leads.tenant_id', '=', 'audit_logs.tenant_id');
            })
            ->where('audit_logs.event', 'lead.stage_changed')
            ->whereBetween('leads.captured_at', [$from, $to]);

        ProjectFilter::apply($query, $type, 'leads.case_id');

        return $query;
    }
}
