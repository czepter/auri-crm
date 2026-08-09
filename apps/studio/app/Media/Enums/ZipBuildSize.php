<?php

namespace App\Media\Enums;

/**
 * Which bytes of each asset a ZipBuild packs. G-03's derivative ladder
 * (400/800/1600/2560px, AVIF+WebP) has no single natural "web" rung on its
 * own, so Web picks the widest available derivative in WebP — the one format
 * of the two G-03 ships that every target platform in #59's acceptance
 * (Explorer, Finder, iOS Files, Files by Google) can at least open, where
 * AVIF support is still patchy on older Windows/Explorer installs.
 */
enum ZipBuildSize: string
{
    case Original = 'original';
    case Web = 'web';
}
