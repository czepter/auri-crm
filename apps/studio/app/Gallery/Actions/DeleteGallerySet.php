<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\GallerySet;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a set with 64 pictures is two decisions, not one: the shelf goes
 * away, and its contents go somewhere. The caller must say where — a
 * silent move would only ever be safe in the one case where the shelf is
 * already empty. Without a target, assets.gallery_set_id's
 * cascadeOnDelete removes the rows, and galleries.cover_asset_id's own
 * nullOnDelete clears the cover if it was among them — both at the
 * database, nothing here has to know about either.
 */
class DeleteGallerySet
{
    public function __invoke(GallerySet $set, ?GallerySet $target): void
    {
        DB::transaction(function () use ($set, $target) {
            if ($target !== null) {
                $set->assets()->update(['gallery_set_id' => $target->id]);
            }

            $set->delete();
        });
    }
}
