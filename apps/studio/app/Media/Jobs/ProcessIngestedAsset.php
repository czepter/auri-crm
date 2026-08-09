<?php

namespace App\Media\Jobs;

use App\Media\Actions\IngestCompletedUpload;
use App\Media\Models\MultipartUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * G-03: the media.ingest queue's only job (config/horizon.php's
 * supervisor-media-ingest, 20s timeout, 3 tries). Dispatched by
 * App\Media\Http\Controllers\MultipartUploadController::complete() the
 * moment a session moves to Completed — the actual work lives in
 * App\Media\Actions\IngestCompletedUpload, kept separate so it is callable
 * directly from a test without a queue round-trip.
 */
class ProcessIngestedAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly MultipartUpload $multipartUpload) {}

    public function handle(IngestCompletedUpload $ingest): void
    {
        $ingest($this->multipartUpload);
    }
}
