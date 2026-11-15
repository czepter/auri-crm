<?php

namespace App\Gallery\Actions;

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Gallery\Support\GallerySettings;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/** The only writer of new galleries outside the legacy import. */
class CreateGallery
{
    /**
     * Was in $attributes steht, gewinnt gegen die Vorlage, und die Vorlage
     * gegen die Studio-Vorgabe: das Formular rechnet die Kette selbst und
     * schickt das Ergebnis, der Klick auf dem Projekt schickt nichts.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes, ?ClientCase $case, ?string $shootPublicId, ?User $actor, ?GalleryPreset $preset = null): Gallery
    {
        $internal = ! ($attributes['is_external'] ?? false);

        return DB::transaction(function () use ($attributes, $case, $shootPublicId, $actor, $preset, $internal) {
            $gallery = Gallery::create([
                ...($internal ? $this->born($preset) : []),
                'project_type_id' => $case?->type?->id,
                ...$attributes,
                'case_id' => $case?->id,
                'shoot_id' => $shootPublicId !== null
                    ? $case?->shoots()->where('public_id', $shootPublicId)->value('id')
                    : null,
                'gallery_preset_id' => $preset?->id,
            ]);

            if ($internal && filled($preset?->sets)) {
                $gallery->sets()->delete();

                foreach ($preset->sets as $position => $set) {
                    $gallery->sets()->create(['name' => $set['name'], 'access' => $set['access'], 'position' => $position]);
                }
            }

            AuditLogger::record(
                event: 'gallery.created',
                actor: $actor,
                context: ['title' => $gallery->title],
                subject: $gallery->public_id,
            );

            return $gallery;
        });
    }

    /** @return array<string, mixed> */
    private function born(?GalleryPreset $preset): array
    {
        $studio = StudioSettings::current();
        $mode = $preset?->expiry_mode ?? 'inherit';
        $days = match ($mode) {
            'days' => $preset->expiry_days,
            'never' => null,
            default => GallerySettings::defaults()['expiryDays'],
        };

        return [
            'design' => GallerySettings::design(),
            'download_rule' => $studio->gallery_default_download_rule,
            'lists_enabled' => (bool) $studio->gallery_default_lists_enabled,
            'comments_enabled' => (bool) $studio->gallery_default_comments_enabled,
            'expires_at' => $days === null ? null : now()->addDays($days)->toDateString(),
            ...($preset?->birthAttributes() ?? []),
        ];
    }
}
