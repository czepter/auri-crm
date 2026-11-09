<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COVER = ['full' => 'zentriert', 'split' => 'geteilt', 'plain' => 'links'];

    private const COLOUR = ['light' => 'hell', 'dark' => 'dunkel', 'warm' => 'sand'];

    private const GRID = [
        'wide' => ['size' => 'mittel', 'spacing' => 'eng', 'flow' => 'zeilen'],
        'dense' => ['size' => 'klein', 'spacing' => 'eng', 'flow' => 'zeilen'],
        'single' => ['size' => 'gross', 'spacing' => 'weit', 'flow' => 'zeilen'],
    ];

    private const TYPE = ['sans', 'serif', 'modern', 'zeitlos', 'kraeftig', 'dezent'];

    private const NEW_COVER = ['zentriert', 'links', 'rahmen', 'geteilt'];

    private const NEW_COLOUR = ['hell', 'gold', 'rose', 'terracotta', 'sand', 'olive', 'agave', 'meer', 'dunkel'];

    /**
     * Fassungen (gallery_versions) werden mit umgeschrieben: der Betrachter
     * liest ihr design, und DraftDeviation vergleicht es mit dem der Galerie.
     * Eingefroren bleiben Bilder und Titel, nicht die Schluesselnamen.
     */
    public function up(): void
    {
        foreach (['galleries', 'gallery_presets', 'gallery_versions', 'studio_settings'] as $table) {
            $column = $table === 'studio_settings' ? 'gallery_default_design' : 'design';

            DB::table($table)->whereNotNull($column)->orderBy('id')->each(function (object $row) use ($table, $column) {
                $design = json_decode($row->{$column}, true);

                if (is_array($design)) {
                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($this->convert($design))]);
                }
            });
        }
    }

    /** @return array<string, string> */
    public function convert(array $design): array
    {
        $next = [];

        if (isset($design['cover'])) {
            $cover = self::COVER[$design['cover']] ?? $design['cover'];
            in_array($cover, self::NEW_COVER, true) && $next['cover'] = $cover;
        }

        if (array_key_exists('type', $design)) {
            $next['type'] = in_array($design['type'], self::TYPE, true) ? $design['type'] : 'sans';
        }

        if (isset($design['colour'])) {
            $colour = self::COLOUR[$design['colour']] ?? $design['colour'];
            in_array($colour, self::NEW_COLOUR, true) && $next['colour'] = $colour;
        }

        if (isset($design['grid'], self::GRID[$design['grid']])) {
            $next += self::GRID[$design['grid']];
        }

        foreach (['size', 'spacing', 'flow'] as $key) {
            isset($design[$key]) && $next[$key] ??= $design[$key];
        }

        return $next;
    }

    public function down(): void {}
};
