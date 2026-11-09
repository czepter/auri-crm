<?php

namespace App\Gallery\Support;

use App\Crm\Models\Task;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Models\GalleryView;
use App\Media\Models\Asset;
use App\Media\Models\DownloadEvent;
use App\Media\Models\ZipBuild;
use Illuminate\Support\Collection;

/**
 * Der Verlauf einer Galerie als eine Zeitleiste. Jede Quelle liefert höchstens
 * LIMIT Zeilen, die Zähler kommen aus eigenen count-Abfragen: die Pillen
 * nennen die wahre Zahl, der Verlauf zeigt die jüngsten Einträge.
 *
 * Gäste bleiben Zahlen. Aufrufe erscheinen als Menge pro Tag, nie mit Namen;
 * Markierungen und Kommentare tragen den Namen nur, wenn ein Kunde angemeldet
 * war.
 */
class GalleryActivity
{
    private const LIMIT = 50;

    private const FAVORITE_PREVIEWS = 12;

    /** @return array<string, mixed> */
    public function __invoke(Gallery $gallery, ?GalleryVersion $version): array
    {
        $numbers = collect($version?->assets ?? [])->pluck('key')->flip();

        $favorites = $version === null ? collect() : AssetFavorite::query()
            ->where('gallery_version_id', $version->id)
            ->with('contact')
            ->latest()
            ->limit(self::LIMIT)
            ->get();
        $comments = $version === null ? collect() : AssetComment::query()
            ->where('gallery_version_id', $version->id)
            ->with(['contact', 'user'])
            ->latest()
            ->limit(self::LIMIT)
            ->get();
        $hides = AssetHide::query()->where('gallery_id', $gallery->id)->with('contact')->latest()->get();
        $keys = $favorites->pluck('asset_key')->merge($comments->pluck('asset_key'))->merge($hides->pluck('asset_key'))->unique()->all();
        $assets = Asset::query()->where('gallery_id', $gallery->id)->whereIn('key', $keys)->get()->keyBy('key');
        $photo = fn (string $key): ?array => isset($assets[$key], $numbers[$key])
            ? ['n' => $numbers[$key] + 1, 'src' => "/galerien/{$gallery->public_id}/bilder/{$assets[$key]->public_id}/vorschau?w=200&fmt=jpeg"]
            : null;
        $downloads = $version === null ? collect() : $this->downloads($version);
        $views = GalleryView::query()
            ->where('gallery_id', $gallery->id)
            ->selectRaw('viewed_on, count(*) as total')
            ->groupBy('viewed_on')
            ->orderByDesc('viewed_on')
            ->limit(self::LIMIT)
            ->get();

        $events = collect()
            ->merge($comments->map(fn (AssetComment $comment) => ($p = $photo($comment->asset_key)) === null ? null : [
                'type' => 'comment', 'at' => $comment->created_at->toIso8601String(),
                'id' => $comment->public_id, 'studio' => $comment->user_id !== null,
                'who' => $comment->user?->name ?? $comment->contact?->name, 'photo' => $p, 'text' => $comment->body,
            ]))
            ->merge($favorites
                ->groupBy(fn (AssetFavorite $favorite) => ($favorite->contact_id ?? $favorite->guest_identifier).'|'.$favorite->created_at->format('Y-m-d'))
                ->map(fn (Collection $group) => [
                    'type' => 'favorites', 'at' => $group->max('created_at')->toIso8601String(),
                    'who' => $group->first()->contact?->name,
                    'photos' => $group->map(fn (AssetFavorite $favorite) => $photo($favorite->asset_key))->filter()->sortBy('n')->values()->all(),
                ])
                ->filter(fn (array $event) => $event['photos'] !== []))
            ->merge($hides->map(fn (AssetHide $hide) => ($p = $photo($hide->asset_key)) === null ? null : [
                'type' => 'hide', 'at' => $hide->created_at->toIso8601String(),
                'id' => $hide->public_id, 'who' => $hide->contact->name, 'photo' => $p,
            ]))
            ->merge($downloads->map(fn (DownloadEvent $event) => [
                'type' => 'download', 'at' => $event->request_date->toIso8601String(),
                'what' => $event->zipBuild?->galleryList?->name, 'size' => $event->zipBuild?->size->value,
            ]))
            ->merge($views->map(fn (GalleryView $view) => [
                'type' => 'views', 'at' => $view->viewed_on->toIso8601String(), 'count' => (int) $view->total,
            ]))
            ->filter()
            ->sortByDesc('at')
            ->values()
            ->all();

        return [
            'counts' => [
                'favorites' => $version === null ? 0 : AssetFavorite::query()->where('gallery_version_id', $version->id)->count(),
                'comments' => $version === null ? 0 : AssetComment::query()->where('gallery_version_id', $version->id)->count(),
                'hidden' => $hides->count(),
                'downloads' => $version === null ? 0 : $this->downloadQuery($version)->count(),
            ],
            'events' => $events,
            'favorites' => $favorites->pluck('asset_key')->unique()->take(self::FAVORITE_PREVIEWS)
                ->map(fn (string $key) => $photo($key))->filter()->values()->all(),
            'hidden' => $hides->map(fn (AssetHide $hide) => ($p = $photo($hide->asset_key)) === null ? null : [
                'id' => $hide->public_id, 'who' => $hide->contact->name, 'photo' => $p,
            ])->filter()->values()->all(),
            'task' => $this->task($gallery),
        ];
    }

    /** Die offene Aufgabe zu den Favoriten, falls es schon eine gibt. */
    public static function taskTitle(Gallery $gallery): string
    {
        return __('gallery.favorites_task', ['title' => $gallery->title ?? '']);
    }

    public static function openTask(Gallery $gallery): ?Task
    {
        return $gallery->case?->tasks()->whereNull('completed_at')->where('title', self::taskTitle($gallery))->first();
    }

    /** @return array{available: bool, url: ?string} */
    private function task(Gallery $gallery): array
    {
        return [
            'available' => $gallery->case_id !== null,
            'url' => $gallery->case_id === null || self::openTask($gallery) === null ? null : "/auftraege/{$gallery->case->public_id}/aufgaben",
        ];
    }

    private function downloadQuery(GalleryVersion $version)
    {
        return DownloadEvent::query()
            ->whereIn('zip_build_id', ZipBuild::query()->where('gallery_version_id', $version->id)->select('id'));
    }

    /** @return Collection<int, DownloadEvent> */
    private function downloads(GalleryVersion $version): Collection
    {
        return $this->downloadQuery($version)->with('zipBuild.galleryList')->latest('request_date')->limit(self::LIMIT)->get();
    }
}
