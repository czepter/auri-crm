<?php

namespace App\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Releases\Support\ReleaseLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What shipped, in the two depths release-log itself is cut for: the feed
 * carries counts per group because one release holds a hundred and fifty
 * entries, and the detail carries the entries.
 *
 * Ungated. Every role runs the same build, and what changed in it is not a
 * studio setting.
 */
class ReleaseController extends Controller
{
    public function index(ReleaseLog $log): Response
    {
        return Inertia::render('Releases/Index', [
            'releases' => $log->feed(),
            'installed' => $log->installed(),
            'mediaBase' => $log->base(),
        ]);
    }

    public function show(string $version, ReleaseLog $log): Response
    {
        $release = $log->release($version);

        abort_if($release === null, 404);

        return Inertia::render('Releases/Show', [
            'release' => $release,
            'installed' => $log->installed(),
            'mediaBase' => $log->base(),
        ]);
    }
}
