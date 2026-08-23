<?php

namespace App\Gallery\Actions;

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Settings\Support\StudioSettingsResolver;
use InvalidArgumentException;

/**
 * X-02: one legacy gallery record, imported one of two ways.
 *
 * Link mode never re-ingests an asset — it points a new Gallery at wherever
 * the legacy system already serves it (external_url), so a decade of
 * galleries costs one row each rather than a full re-upload.
 *
 * Backfill mode copies the record's assets straight into a Gallery's draft
 * columns and calls Gallery::publish() once, producing a real version 1 the
 * same way any in-app gallery gets one (G-05) — appropriate for the handful
 * of legacy galleries worth actually owning going forward.
 *
 * Both modes key off (project, shoot, title) to decide whether a record was
 * already imported: re-running the same fixture file is a no-op on the
 * second pass rather than a duplicate row.
 */
class ImportLegacyGallery
{
    /**
     * @param  array<string, mixed>  $record
     * @return array{status: string, message: string, gallery: ?Gallery}
     */
    public function __invoke(array $record, string $mode, bool $dryRun = false): array
    {
        if (! in_array($mode, ['link', 'backfill'], true)) {
            throw new InvalidArgumentException("Unknown mode '{$mode}' — expected 'link' or 'backfill'.");
        }

        $project = $this->resolveProject($record);
        if (! $project) {
            return $this->result('error', "project_public_id '".($record['project_public_id'] ?? '')."' does not match any project.");
        }

        $shoot = $this->resolveShoot($record, $project);
        if ($shoot === false) {
            return $this->result('error', "shoot_public_id '{$record['shoot_public_id']}' does not belong to project '{$project->public_id}'.");
        }

        $title = $record['title'] ?? null;

        return $mode === 'link'
            ? $this->link($record, $project, $shoot, $title, $dryRun)
            : $this->backfill($record, $project, $shoot, $title, $dryRun);
    }

    private function link(array $record, Project $project, ?Shoot $shoot, ?string $title, bool $dryRun): array
    {
        $externalUrl = $record['external_url'] ?? null;
        if (! $externalUrl) {
            return $this->result('error', 'link mode requires external_url.');
        }

        $existing = Gallery::query()
            ->where('project_id', $project->id)
            ->where('shoot_id', $shoot?->id)
            ->where('is_external', true)
            ->where('external_url', $externalUrl)
            ->first();

        if ($existing) {
            return $this->result('skipped', 'already linked.', $existing);
        }

        if ($dryRun) {
            return $this->result('would-link', "would link to {$externalUrl}.");
        }

        $gallery = Gallery::create([
            'project_id' => $project->id,
            'shoot_id' => $shoot?->id,
            'title' => $title,
            'is_external' => true,
            'external_url' => $externalUrl,
        ]);

        return $this->result('linked', "linked to {$externalUrl}.", $gallery);
    }

    private function backfill(array $record, Project $project, ?Shoot $shoot, ?string $title, bool $dryRun): array
    {
        $assets = $record['assets'] ?? [];
        if (! is_array($assets) || $assets === []) {
            return $this->result('error', 'backfill mode requires a non-empty assets array.');
        }

        $existing = Gallery::query()
            ->where('project_id', $project->id)
            ->where('shoot_id', $shoot?->id)
            ->where('is_external', false)
            ->where('title', $title)
            ->first();

        if ($existing) {
            return $this->result('skipped', 'already backfilled.', $existing);
        }

        if ($dryRun) {
            return $this->result('would-backfill', 'would create a gallery with '.count($assets).' asset(s) and publish version 1.');
        }

        $gallery = Gallery::create([
            'project_id' => $project->id,
            'shoot_id' => $shoot?->id,
            'title' => $title,
            'assets' => $assets,
            // P-09 (#228): the studio's own defaults are the starting values,
            // never a lock — rotateViewPassword() and the rest still override
            // per gallery, and a later change to the default deliberately
            // leaves galleries already out there alone.
            'requires_settled_balance' => app(StudioSettingsResolver::class)
                ->galleryDefaults()['requiresSettledBalance'],
        ]);

        // publish() snapshots real Asset rows (Gallery::draftAssets()), not
        // this legacy assets array — a legacy filename never went through
        // ingestion, so this is the one place that stands in for it.
        $set = $gallery->sets()->first();
        foreach ($assets as $key) {
            Asset::create([
                'gallery_id' => $gallery->id,
                'gallery_set_id' => $set->id,
                'status' => AssetStatus::Ready,
                'key' => $key,
                'filename' => basename($key),
            ]);
        }

        $gallery->publish();

        return $this->result('backfilled', 'created and published version 1 with '.count($assets).' asset(s).', $gallery);
    }

    private function resolveProject(array $record): ?Project
    {
        $publicId = $record['project_public_id'] ?? null;

        return $publicId ? Project::findByPublicId($publicId) : null;
    }

    /** Returns null (no shoot given), a Shoot, or false (given but invalid/mismatched). */
    private function resolveShoot(array $record, Project $project): Shoot|false|null
    {
        $publicId = $record['shoot_public_id'] ?? null;
        if (! $publicId) {
            return null;
        }

        $shoot = Shoot::findByPublicId($publicId);

        return $shoot && $shoot->project_id === $project->id ? $shoot : false;
    }

    private function result(string $status, string $message, ?Gallery $gallery = null): array
    {
        return ['status' => $status, 'message' => $message, 'gallery' => $gallery];
    }
}
