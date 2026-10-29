<?php

namespace App\Gallery\Actions;

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Support\AuditLogger;

/** The only writer of new galleries outside the legacy import. */
class CreateGallery
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(array $attributes, ?ClientCase $case, ?string $shootPublicId, ?User $actor): Gallery
    {
        $gallery = Gallery::create([
            ...$attributes,
            'case_id' => $case?->id,
            'shoot_id' => $shootPublicId !== null
                ? $case?->shoots()->where('public_id', $shootPublicId)->value('id')
                : null,
        ]);

        AuditLogger::record(
            event: 'gallery.created',
            actor: $actor,
            context: ['title' => $gallery->title],
            subject: $gallery->public_id,
        );

        return $gallery;
    }
}
