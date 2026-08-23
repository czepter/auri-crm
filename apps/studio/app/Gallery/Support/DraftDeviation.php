<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;

/**
 * Ob der Entwurf von der stehenden Version abweicht — bei jedem Aufruf frisch
 * gerechnet statt in einer Spalte gepflegt, die eine kuenftige Action
 * vergessen kann und die dann still falsch steht.
 *
 * Nicht die Anzahl: ein Bild raus, eins rein ergibt dieselbe Zahl und einen
 * anderen Inhalt.
 */
class DraftDeviation
{
    public static function of(Gallery $gallery): bool
    {
        $version = $gallery->currentVersion;

        if ($version === null) {
            return true;
        }

        $draft = $gallery->draftAssets()->map(fn (array $entry) => "{$entry['set']}/{$entry['key']}")->all();
        $published = collect($version->assets)->map(fn (array $entry) => ($entry['set'] ?? '')."/{$entry['key']}")->all();

        return $draft !== $published
            || $gallery->title !== $version->title
            // Loose on purpose: both sides are the same array-cast JSON
            // column, and a save that re-emits identical settings in a
            // different key order must not read as a deviation.
            || $gallery->design != $version->design
            || $gallery->coverAsset?->key !== $version->cover_key;
    }
}
