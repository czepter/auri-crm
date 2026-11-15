<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Models\ProjectType;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryDesign;
use App\Gallery\Support\GallerySettings;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Portal\Support\LegalTexts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the gallery host answers at `/`: the studio's choice of nothing, a
 * redirect or the overview of galleries that opted in. Gallery::listedOnHome()
 * is the only judge of who appears; every response is no-store so a withdrawn
 * gallery never lingers in a cache.
 */
class GalleryHomeController extends Controller
{
    private const STEP = 24;

    private const MAX_PAGE = 10_000;

    public function __invoke(Request $request): JsonResponse
    {
        $settings = StudioSettings::current();

        $payload = match ($settings->gallery_home_mode) {
            'redirect' => ['mode' => 'redirect', 'url' => $settings->gallery_home_redirect_url],
            'overview' => $this->overview($request, $settings),
            default => ['mode' => 'none'],
        };

        return response()->json($payload)->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function overview(Request $request, StudioSettings $settings): array
    {
        $address = $request->query('adresse');

        if (is_string($address)) {
            [$prefix, $slug] = array_pad(explode('/', $address, 2), 2, '');

            $gallery = Gallery::query()->listedOnHome()->where('prefix', $prefix)->where('slug', $slug)->first();

            return [
                'mode' => 'overview',
                'galleries' => $gallery === null ? [] : [$this->tile($gallery)],
            ];
        }

        $types = $this->categories();
        $slugs = $types->mapWithKeys(fn (array $c) => [$c['slug'] => $c['id']]);
        $requested = $request->query('kategorie');
        $category = is_string($requested) && $slugs->has($requested) ? $requested : null;

        $page = min(max(1, (int) $request->query('seite', 1)), self::MAX_PAGE);
        $limit = self::STEP * $page;

        $galleries = Gallery::query()->listedOnHome()
            ->when($category !== null, fn ($q) => $q->where('galleries.project_type_id', $slugs[$category]))
            ->orderedForHome()
            ->with(['currentVersion', 'shoot', 'projectType'])
            ->limit($limit + 1)
            ->get();

        $logo = $settings->logo_path ? rtrim((string) config('portal.url'), '/').'/branding/logo' : null;

        return [
            'mode' => 'overview',
            'noindex' => (bool) $settings->gallery_home_noindex,
            'studio' => [
                'name' => $settings->display_name ?? config('app.name'),
                'logo' => $logo,
                'website' => $settings->website,
                'impressum' => $this->legalUrl('impressum'),
                'datenschutz' => $this->legalUrl('datenschutz'),
            ],
            'design' => GalleryDesign::resolve(GallerySettings::design()),
            'categories' => $types->map(fn (array $c) => ['slug' => $c['slug'], 'name' => $c['name']])->values()->all(),
            'category' => $category,
            'more' => $galleries->count() > $limit,
            'galleries' => $galleries->take($limit)->map(fn (Gallery $g) => $this->tile($g))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function tile(Gallery $gallery): array
    {
        $version = $gallery->currentVersion;
        $key = $gallery->homeCoverKey($version);

        return [
            'title' => $version->title,
            'date' => $gallery->occursOn()?->toDateString(),
            'category' => $gallery->projectType?->name,
            'path' => "{$gallery->prefix}/{$gallery->slug}",
            'version' => $version->public_id,
            'cover' => $key !== null,
            'protected' => $gallery->requiresViewPassword(),
            'focal' => $key === null ? null : $gallery->coverFor($key)['focal'],
        ];
    }

    /**
     * Types with at least one listed gallery, in the studio's order. Two types
     * that slug alike: the later one falls back to its key.
     *
     * @return Collection<int, array{id: int, slug: string, name: string}>
     */
    private function categories(): Collection
    {
        $listed = Gallery::query()->listedOnHome()->whereNotNull('galleries.project_type_id')->select('galleries.project_type_id');

        $taken = [];

        return ProjectType::query()->whereIn('id', $listed)->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (ProjectType $type) use (&$taken) {
                $slug = Str::slug($type->name);
                $slug = in_array($slug, $taken, true) ? $type->key : $slug;
                $taken[] = $slug;

                return ['id' => $type->id, 'slug' => $slug, 'name' => $type->name];
            });
    }

    private function legalUrl(string $text): ?string
    {
        $url = LegalTexts::url($text);

        return $url !== null && str_starts_with($url, '/') ? rtrim((string) config('portal.url'), '/').$url : $url;
    }
}
