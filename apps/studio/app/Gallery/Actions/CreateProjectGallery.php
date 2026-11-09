<?php

namespace App\Gallery\Actions;

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Models\User;

/**
 * Die Galerie eines Projekts ohne Formular: Titel und Datum kommen vom
 * Shooting oder vom Projekt, die Vorlage vom gebuchten Paket, sonst von der
 * Standardvorlage.
 */
class CreateProjectGallery
{
    public function __construct(private readonly CreateGallery $create) {}

    public function __invoke(ClientCase $case, ?string $shootPublicId, ?User $actor): Gallery
    {
        $shoot = $shootPublicId === null
            ? null
            : $case->shoots()->where('public_id', $shootPublicId)->firstOrFail();

        $preset = $case->bookedPackage()?->galleryPreset
            ?? GalleryPreset::query()->where('is_default', true)->first();

        return ($this->create)(
            [
                'title' => $shoot ? ($shoot->label ?? $shoot->kind) : $case->name,
                // galleries_one_date: ein Shooting trägt das Datum, sonst das Projekt.
                'event_date' => $shoot ? null : $case->primaryShoot?->date,
                'is_external' => false,
            ],
            $case,
            $shoot?->public_id,
            $actor,
            $preset,
        );
    }
}
