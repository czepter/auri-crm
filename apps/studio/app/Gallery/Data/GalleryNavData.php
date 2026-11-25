<?php

namespace App\Gallery\Data;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Models\GalleryVersion;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Die Spalte des Datensatzes: dasselbe `tabs` wie am Projekt
 * (CaseHeaderData), dazu zwei Dinge, die ein Projekt nicht hat.
 *
 * Das Titelbild steht darüber, weil es kein Bereich ist, sondern das Gesicht
 * des Datensatzes — dieselbe Stelle, an der ein Kontakt seinen Avatar trägt.
 *
 * Die Sets hängen als `children` unter Bilder statt als zweite Liste
 * darunter: sie sind die Gliederung des ersten Bereichs, keine fünfte.
 */
#[TypeScript]
class GalleryNavData extends Data
{
    /**
     * @param  list<array{key: string, label: string, href: string, count: int|null, countTo: int|null, children: list<array{key: string, label: string, translate: bool, href: string, count: int|null, reorderable: bool, tag: string|null}>}>  $tabs
     */
    public function __construct(
        public ?string $cover,
        public array $tabs,
    ) {}

    /** @param  Collection<int, GallerySet>  $sets */
    public static function build(Gallery $gallery, Collection $sets, ?GalleryVersion $version): self
    {
        $base = "/galerien/{$gallery->public_id}";
        $draft = (int) $sets->sum('assets_count');

        // Was beim Kunden liegt, nicht was im Entwurf liegt — die Zahl steht
        // neben einer Navigation und beantwortet „wie viel ist draußen".
        // Ohne Fassung ist die ehrliche Antwort keine Null, sondern gar
        // nichts; die Sets tragen ihre eigenen Zähler.
        $published = $version === null ? null : count($version->assets ?? []);

        return new self(
            cover: $gallery->coverAsset === null
                ? null
                : "{$base}/bilder/{$gallery->coverAsset->public_id}/vorschau",
            tabs: [
                [
                    'key' => 'bilder',
                    'label' => 'galleryRecord.tab.bilder',
                    'href' => "{$base}/bilder",
                    'count' => $published,
                    // Ohne das ist ein Upload, der nie veröffentlicht wurde,
                    // in der Navigation unsichtbar.
                    'countTo' => $published !== null && $published !== $draft ? $draft : null,
                    'children' => self::sets($gallery, $sets),
                ],
                ['key' => 'gestaltung', 'label' => 'galleryRecord.tab.gestaltung', 'href' => "{$base}/gestaltung", 'count' => null, 'countTo' => null, 'children' => []],
                ['key' => 'einstellungen', 'label' => 'galleryRecord.tab.einstellungen', 'href' => "{$base}/einstellungen/zugang", 'count' => null, 'countTo' => null, 'children' => []],
                [
                    'key' => 'aktivitaet',
                    'label' => 'galleryRecord.tab.aktivitaet',
                    'href' => "{$base}/aktivitaet",
                    // Markierungen und Kommentare, nicht Aufrufe: ein Zähler
                    // taugt nur für eine Menge, die man abarbeiten kann. Er
                    // hängt an der Fassung und fängt mit jeder neuen von vorn
                    // an.
                    'count' => $version === null ? null : AssetFavorite::query()->where('gallery_version_id', $version->id)->count()
                        + AssetComment::query()->where('gallery_version_id', $version->id)->count(),
                    'countTo' => null,
                    'children' => [],
                ],
            ],
        );
    }

    /**
     * @param  Collection<int, GallerySet>  $sets
     * @return list<array{key: string, label: string, translate: bool, href: string, count: int|null, reorderable: bool, tag: string|null}>
     */
    private static function sets(Gallery $gallery, Collection $sets): array
    {
        $base = "/galerien/{$gallery->public_id}/bilder";

        // translate trennt Schlüssel von Klartext: „Alle" ist ein Wort der
        // Oberfläche, „Trauung" der Name, den jemand getippt hat.
        $rows = $sets->map(fn (GallerySet $set) => [
            'key' => $set->public_id,
            'label' => $set->name,
            'translate' => false,
            'href' => "{$base}/{$set->public_id}",
            'count' => null,
            'reorderable' => true,
            'tag' => $set->isForGuests() ? 'galleryRecord.guestsTag' : null,
        ])->all();

        // Erst ab dem zweiten Set: bei einem stünde dort dasselbe zweimal.
        if (count($rows) < 2) {
            return $rows;
        }

        return [
            ['key' => 'alle', 'label' => 'gallery.all', 'translate' => true, 'href' => $base, 'count' => null, 'reorderable' => false, 'tag' => null],
            ...$rows,
        ];
    }
}
