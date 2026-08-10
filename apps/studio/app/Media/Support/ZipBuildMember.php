<?php

namespace App\Media\Support;

/**
 * One file App\Media\Actions\BuildGalleryZip reads from the `s3` disk and
 * writes into an archive volume: which object to read ($key), what to name
 * it inside the zip ($name), and its size in bytes — known up front from
 * Asset::byte_size / AssetDerivative::bytes, which is what lets
 * App\Media\Support\ZipVolumePlanner plan volumes without touching storage.
 */
final readonly class ZipBuildMember
{
    public function __construct(
        public string $key,
        public string $name,
        public int $size,
    ) {}
}
