<?php

namespace App\Gallery\Jobs;

use App\Gallery\Actions\CopyAssetToGallery;
use App\Gallery\Models\Gallery;
use App\Media\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Ein Bild je Job: ein Abbruch kostet höchstens dieses eine, und media.ingest hat 20 Sekunden Zeit je Job. */
class CopyGalleryAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Gallery $target, public readonly Asset $source)
    {
        $this->onQueue('media.ingest');
    }

    public function handle(CopyAssetToGallery $copy): void
    {
        $copy($this->target, $this->source);
    }
}
