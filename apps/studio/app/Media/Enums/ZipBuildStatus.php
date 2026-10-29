<?php

namespace App\Media\Enums;

/**
 * building -> ready|failed, and never back. A Failed row is retried by
 * flipping it back to Building in place (App\Gallery\Actions\RequestZipBuild)
 * rather than inserting a second row — the unique (tenant, gallery_version,
 * scope, size) index only allows one row per triple ever to exist.
 */
enum ZipBuildStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
