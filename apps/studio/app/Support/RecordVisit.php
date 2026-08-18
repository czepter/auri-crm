<?php

namespace App\Support;

use App\Models\RecentView;

/**
 * C-03 (#237): remember that this user opened this record.
 *
 * Written where the record is displayed, read when the palette opens. No
 * service, no event stream — the whole feature is one upsert and one ordered
 * select, and anything more would be scaffolding for a history nobody has
 * asked to do anything else with.
 *
 * Trimming happens on write rather than on read: the list is capped at twenty
 * whether or not anyone opens the palette, so a user who works for a year
 * carries twenty rows and not a year of them.
 */
class RecordVisit
{
    /** contact · anfrage · projekt · galerie — the four the palette names in its placeholder. */
    public static function record(string $kind, string $subjectId, string $title): void
    {
        $user = auth('staff')->user();

        if ($user === null) {
            return;
        }

        RecentView::query()->upsert(
            [[
                'tenant_id' => config('tenancy.tenant_id'),
                'user_id' => $user->id,
                'kind' => $kind,
                'subject_id' => $subjectId,
                'title' => $title,
                'viewed_at' => now(),
            ]],
            ['tenant_id', 'user_id', 'subject_id'],
            ['kind', 'title', 'viewed_at'],
        );

        $keep = RecentView::query()
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->limit(RecentView::KEPT)
            ->pluck('id');

        RecentView::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * The palette's own list. Mixed in visit order rather than grouped by
     * kind: grouped, it would be sorted by something nobody is looking for.
     *
     * @return list<array{kind: string, title: string, href: string, at: string}>
     */
    public static function recent(): array
    {
        $user = auth('staff')->user();

        if ($user === null) {
            return [];
        }

        return RecentView::query()
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->limit(RecentView::KEPT)
            ->get()
            ->map(fn (RecentView $view) => [
                'kind' => $view->kind,
                'title' => $view->title,
                'href' => self::href($view->kind, $view->subject_id),
                'at' => $view->viewed_at->toIso8601String(),
            ])
            ->all();
    }

    private static function href(string $kind, string $subjectId): string
    {
        return match ($kind) {
            'kontakt' => "/kontakte/{$subjectId}",
            'galerie' => "/galerien/{$subjectId}/medien",
            default => "/auftraege/{$subjectId}",
        };
    }
}
