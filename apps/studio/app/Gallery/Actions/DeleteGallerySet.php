<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\GallerySet;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a set with 64 pictures is two decisions, not one: the shelf goes
 * away, and its contents go somewhere. The caller must say where.
 *
 * "Bilder mit löschen" (no target) still does not hard-delete: this is why
 * the gallery schema has no SoftDeletes (R23) — assets.gallery_set_id's
 * cascadeOnDelete would remove the rows outright, and BuildGalleryZip
 * resolves a published version's keys straight through Asset rows, so a
 * delivered gallery's ZIP would build with zero members the moment its
 * set is cleaned up. The rows move to the gallery's remaining set — the one
 * with the lowest position, the same one a manual merge would land on — and
 * leave the draft via removed_from_draft_at, exactly as a single-picture
 * removal does. Which of several remaining sets receives them makes no
 * difference to anything that reads gallery_set_id off a removed row: draft
 * views already filter the column out, and BuildGalleryZip never looks at
 * it.
 */
class DeleteGallerySet
{
    public function __invoke(GallerySet $set, ?GallerySet $target): void
    {
        DB::transaction(function () use ($set, $target) {
            if ($target !== null) {
                $set->assets()->update(['gallery_set_id' => $target->id]);
            } else {
                $gallery = $set->gallery;
                $landing = $gallery->sets()->whereKeyNot($set->id)->orderBy('position')->firstOrFail();
                $removedIds = $set->assets()->pluck('id');

                $set->assets()->update(['gallery_set_id' => $landing->id, 'removed_from_draft_at' => now()]);

                // The row survives, so nullOnDelete never fires for it —
                // mirrors GalleryAssetController::remove()'s own explicit
                // clear for the single-picture case.
                if ($removedIds->contains($gallery->cover_asset_id)) {
                    $gallery->update(['cover_asset_id' => null, 'cover_focal_x' => null, 'cover_focal_y' => null]);
                }
            }

            $set->delete();
        });
    }
}
