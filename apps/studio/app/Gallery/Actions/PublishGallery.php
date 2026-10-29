<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Notifications\GalleryPrereleasePublished;
use App\Gallery\Support\DraftDeviation;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Ein Knopf, zwei Ausgänge. Eine Version, die sich in nichts von ihrer
 * Vorgaengerin unterscheidet, waere Rauschen in einer Historie, die ein Paar
 * ueber Jahre begleitet.
 */
class PublishGallery
{
    public function __invoke(Gallery $gallery): ?GalleryVersion
    {
        if (! DraftDeviation::of($gallery)) {
            $gallery->forceFill(['unpublished_at' => null, 'prerelease_at' => null])->save();

            // R45: a publish that writes no new version still ends
            // pre-release (its draft already matches what's live) and
            // still owes every waiting address its one mail — this branch
            // used to return before ever calling notifyPrereleaseSignups(),
            // which meant a publish that raced a failed one, or simply
            // found nothing to change, silently dropped the whole list.
            $this->notifyPrereleaseSignups($gallery);

            return null;
        }

        if ($gallery->draftAssets()->isEmpty()) {
            throw ValidationException::withMessages(['gallery' => __('gallery.empty_draft')]);
        }

        $version = $gallery->publish();

        $gallery->forceFill(['unpublished_at' => null, 'prerelease_at' => null])->save();

        $this->notifyPrereleaseSignups($gallery);

        return $version;
    }

    /**
     * R42/R45: one mail per address, ever. Claiming and removing the rows
     * happens inside one transaction with `lockForUpdate()`, so two
     * publishes racing each other serialize on it — the second finds
     * nothing left to claim rather than a window in which both read the
     * same unnotified rows.
     *
     * Deleted, not merely marked: R45 asked for the claim to be atomic and
     * recoverable, and the "personal data" review that followed asked what
     * happens to a signup once its one purpose is served. Nothing in this
     * codebase reads a notified signup afterwards — the row's only job was
     * holding an address until this moment — so there is nothing to keep.
     * `notified_at`/`notNotified()` still name the scope correctly (a row
     * that exists is, by construction, always unnotified); this is the
     * fix for R42's stated retention gap, see GalleryPrereleaseSignup's own
     * docblock for the sibling decision at pre-release's other exit.
     *
     * `->afterCommit()`: the notification is only ever dispatched to the
     * queue once this transaction actually commits, so a crash between the
     * delete and the mail leaves nothing sent and nothing lost — the
     * transaction rolls back, the rows are still there, and the next
     * publish (or a retried request) claims them again. Independent of
     * DraftDeviation on purpose, called from both of this action's two
     * publish paths above.
     */
    private function notifyPrereleaseSignups(Gallery $gallery): void
    {
        DB::transaction(function () use ($gallery) {
            $signups = $gallery->prereleaseSignups()->notNotified()->lockForUpdate()->get();

            if ($signups->isEmpty()) {
                return;
            }

            $gallery->prereleaseSignups()->whereIn('id', $signups->pluck('id'))->delete();

            $settings = app(StudioSettingsResolver::class);

            foreach ($signups as $signup) {
                Notification::route('mail', $signup->email)
                    ->notify((new GalleryPrereleasePublished($gallery))->afterCommit());
            }
        });
    }
}
