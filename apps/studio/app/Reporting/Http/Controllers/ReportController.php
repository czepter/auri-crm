<?php

namespace App\Reporting\Http\Controllers;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Quotes\Support\QuoteAcceptanceRate;
use App\Reporting\Support\BookingFunnel;
use App\Reporting\Support\CashFlowForecast;
use App\Reporting\Support\RevenueReport;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S-13's Auswertungen, with M-07's Liquiditätsplanung as one of its panels
 * rather than a screen of its own — a studio asking "how are we doing" and a
 * studio asking "what is coming in" is the same studio at the same moment.
 *
 * Every window is the trailing twelve months against the twelve before it,
 * so the Vorjahresvergleich is the same query run twice rather than a second
 * definition of a year.
 *
 * Authority: ViewAllProjects. These figures aggregate the whole studio's
 * book, so a second shooter who only reaches the projects they are crewed on
 * has no business reading them — and a per-role filtered aggregate would be
 * a number that means something different depending who asks, which is worse
 * than no number.
 */
class ReportController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(
            auth('staff')->user()?->role->allows(Capability::ViewAllProjects) ?? false,
            403,
        );

        $to = now();
        $from = $to->copy()->subYear();
        $priorTo = $from->copy();
        $priorFrom = $priorTo->copy()->subYear();

        return Inertia::render('Auswertungen', [
            'window' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],

            'funnelByMonth' => BookingFunnel::byMonth($from, $to),
            'sources' => BookingFunnel::bySource($from, $to),
            'referrers' => BookingFunnel::referrers($from, $to),
            'lostReasons' => BookingFunnel::lostReasons($from, $to),

            'acceptanceRate' => QuoteAcceptanceRate::between($from, $to),
            'acceptanceRatePrior' => QuoteAcceptanceRate::between($priorFrom, $priorTo),

            'revenue' => RevenueReport::forWindow($from, $to),
            'revenuePrior' => RevenueReport::forWindow($priorFrom, $priorTo),
            'perContact' => RevenueReport::perContact($from, $to),

            'cashFlow' => CashFlowForecast::build(),
        ]);
    }
}
