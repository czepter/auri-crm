<?php

namespace App\Media\Support;

/**
 * Pure grouping logic for #59's split-at-3.9GiB rule — no storage, no zip
 * writing, so the boundary itself is testable without any real I/O (or a 4GB
 * fixture) by injecting a small $thresholdBytes.
 */
final class ZipVolumePlanner
{
    /**
     * Groups members into archive volumes so that no in-progress archive's
     * cumulative size would cross $thresholdBytes — once adding the next
     * member would, the current volume is closed and a new one started with
     * that member.
     *
     * A single member already at or beyond the threshold still gets a
     * volume of its own rather than being split: a zip member can't be torn
     * across two archives and remain valid in either.
     *
     * Ignores each member's own handful of bytes of zip-format overhead
     * (local header, data descriptor) — negligible against a multi-GiB
     * threshold, and 3.9GiB is an interoperability target, not a hard
     * protocol limit.
     *
     * @param  list<ZipBuildMember>  $members
     * @return list<list<ZipBuildMember>>
     */
    public static function plan(array $members, int $thresholdBytes): array
    {
        $volumes = [];
        $current = [];
        $currentSize = 0;

        foreach ($members as $member) {
            if ($current !== [] && $currentSize + $member->size > $thresholdBytes) {
                $volumes[] = $current;
                $current = [];
                $currentSize = 0;
            }

            $current[] = $member;
            $currentSize += $member->size;
        }

        if ($current !== []) {
            $volumes[] = $current;
        }

        return $volumes;
    }
}
