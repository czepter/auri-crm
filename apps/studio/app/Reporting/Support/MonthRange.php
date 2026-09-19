<?php

namespace App\Reporting\Support;

use Carbon\CarbonInterface;

/**
 * Die Monate eines Auswertungsfensters, lückenlos und ältester zuerst.
 *
 * Eine Gruppierung nach Monat liefert nur die Monate, in denen etwas passiert
 * ist. Ein Diagramm daraus staucht ein Jahr auf die zwei Monate mit Daten und
 * lässt die leeren wie nicht vorhanden aussehen statt wie leer.
 */
class MonthRange
{
    /**
     * @return list<string> 'YYYY-MM'
     */
    public static function keys(CarbonInterface $from, CarbonInterface $to): array
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();

        while ($cursor <= $to) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $months;
    }
}
