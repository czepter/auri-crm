<?php

namespace App\Gallery\Jobs;

use App\Gallery\Actions\BuildGalleryZip;
use App\Media\Models\ZipBuild;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * G-10 (#59): the media.zip queue's only job (config/horizon.php's
 * supervisor-media-zip, 40min timeout). Dispatched by App\Media\Actions\
 * RequestZipBuild — the actual work lives in App\Media\Actions\
 * BuildGalleryZip, kept separate so it's callable directly from a test
 * without a queue round-trip.
 */
class BuildZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ZipBuild $zipBuild) {}

    public function handle(BuildGalleryZip $build): void
    {
        $build($this->zipBuild);
    }
}
