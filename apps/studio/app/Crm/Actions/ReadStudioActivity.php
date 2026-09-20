<?php

namespace App\Crm\Actions;

use App\Crm\Models\Project;
use App\Crm\Support\ProjectActivity;
use App\Gallery\Models\GalleryView;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The Übersicht's Verlauf: what moved across every project the viewer may
 * open, newest first.
 *
 * Audit rows pass through the project history's own allow-list, so nothing
 * shows here that the project would hide. Gallery openings come from
 * GalleryView, which records a day rather than a moment — those lines carry
 * no time and are never marked fresh.
 */
class ReadStudioActivity
{
    private const LIMIT = 8;

    /** Project tab per event prefix; lead and project events open the overview. The payment plan lives on the quotes tab. */
    private const TABS = [
        'quote' => 'angebote',
        'payment_plan' => 'angebote',
        'payment_installment' => 'angebote',
        'shoot' => 'shootings',
        'form_instance' => 'frageboegen',
    ];

    /** @return list<array{text: string, href: string, when: string, fresh: bool}> */
    public function __invoke(User $viewer, CarbonInterface $since): array
    {
        $visible = Project::query()->visibleTo($viewer)->select('projects.id');

        $events = AuditLog::query()
            ->whereIn('project_id', $visible)
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            // ponytail: over-reads so the allow-list still leaves LIMIT rows; a window of mostly hidden events shows fewer.
            ->limit(self::LIMIT * 4)
            ->get()
            ->filter(fn (AuditLog $log) => ProjectActivity::isShown($log))
            ->map(fn (AuditLog $log) => [
                'project' => $log->project_id,
                'label' => ProjectActivity::label($log),
                'tab' => self::TABS[Str::before($log->event, '.')] ?? null,
                'at' => $log->created_at,
                'timed' => true,
            ]);

        $views = GalleryView::query()
            ->whereHas('gallery', fn ($query) => $query->whereIn('project_id', $visible))
            ->whereDate('viewed_on', '>=', $since->toDateString())
            ->with('gallery:id,project_id')
            ->orderByDesc('viewed_on')
            ->limit(self::LIMIT * 4)
            ->get()
            // One line per gallery per day, whoever and however often opened it.
            ->unique(fn (GalleryView $view) => $view->gallery_id.'|'.$view->viewed_on->toDateString())
            ->map(fn (GalleryView $view) => [
                'project' => $view->gallery->project_id,
                'label' => __('activity.gallery.viewed'),
                'tab' => 'galerien',
                'at' => $view->viewed_on,
                'timed' => false,
            ]);

        $projects = Project::query()
            ->whereIn('id', $events->pluck('project')->concat($views->pluck('project'))->unique())
            ->get(['id', 'name', 'public_id'])
            ->keyBy('id');

        return $events->concat($views)
            ->sortByDesc(fn (array $row) => $row['at']->getTimestamp())
            ->take(self::LIMIT)
            ->map(fn (array $row) => [
                'text' => $projects[$row['project']]->name.' · '.$row['label'],
                'href' => '/auftraege/'.$projects[$row['project']]->public_id.($row['tab'] ? '/'.$row['tab'] : ''),
                'when' => $this->when($row['at'], $row['timed']),
                'fresh' => $row['timed'] && $row['at']->greaterThan(now()->subDay()),
            ])
            ->values()
            ->all();
    }

    private function when(CarbonInterface $at, bool $timed): string
    {
        $day = match (true) {
            $at->isToday() => 'heute',
            $at->isYesterday() => 'gestern',
            $at->greaterThan(now()->subWeek()) => $at->locale('de')->dayName,
            default => $at->format('d.m.'),
        };

        return $timed && ($at->isToday() || $at->isYesterday()) ? "{$day}, {$at->format('H:i')}" : $day;
    }
}
