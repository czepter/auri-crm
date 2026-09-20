<?php

namespace App\Reporting\Enums;

use App\Crm\Enums\HasLabel;
use Carbon\CarbonImmutable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Der Zeitraum, über den die Auswertungen rechnen.
 *
 * Der Vergleichszeitraum ist immer derselbe Zeitraum davor, nie ein starres
 * Vorjahr: sonst vergleicht "Dieses Jahr" im März zehn Monate mit zwei.
 */
#[TypeScript]
enum ReportPeriod: string implements HasLabel
{
    case TwelveMonths = '12m';
    case SixMonths = '6m';
    case YearToDate = 'jahr';

    public function label(): string
    {
        return match ($this) {
            self::TwelveMonths => 'Letzte 12 Monate',
            self::SixMonths => 'Letzte 6 Monate',
            self::YearToDate => 'Dieses Jahr',
        };
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable} */
    public function window(?CarbonImmutable $now = null): array
    {
        $to = $now ?? CarbonImmutable::now();

        return [
            'from' => match ($this) {
                self::TwelveMonths => $to->subYear(),
                self::SixMonths => $to->subMonths(6),
                self::YearToDate => $to->startOfYear(),
            },
            'to' => $to,
        ];
    }

    /**
     * Derselbe Zeitraum davor, in der Einheit des Zeitraums gerechnet: sechs
     * Monate zurück sind sechs Kalendermonate, nicht 184 Tage.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function priorWindow(?CarbonImmutable $now = null): array
    {
        ['from' => $from, 'to' => $to] = $this->window($now);

        return match ($this) {
            self::TwelveMonths => ['from' => $from->subYear(), 'to' => $from],
            self::SixMonths => ['from' => $from->subMonths(6), 'to' => $from],
            // Zwei Monate dieses Jahres gehören gegen dieselben zwei des
            // Vorjahres, nicht gegen die zwei davor.
            self::YearToDate => ['from' => $from->subYear(), 'to' => $to->subYear()],
        };
    }
}
