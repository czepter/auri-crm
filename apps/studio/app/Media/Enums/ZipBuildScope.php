<?php

namespace App\Media\Enums;

/**
 * Which of a GalleryVersion's assets a ZipBuild includes. One case for now —
 * every asset the version snapshot carries — per #59's own steer: "keep it
 * simple, a single 'all' scope is fine". G-11/G-12 (favorites-only, etc.) add
 * cases here rather than inventing a second concept.
 */
enum ZipBuildScope: string
{
    case All = 'all';
}
