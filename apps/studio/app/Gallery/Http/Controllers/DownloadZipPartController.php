<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Actions\RecordDownloadEvent;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Media\Support\ByteRange;
use App\Media\Support\RangeNotSatisfiableException;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * G-11 (#60): the only way a built ZIP volume's bytes leave the `s3` disk —
 * same rule GalleryImageController already enforces for image bytes
 * (G-01a's docs/media.md, "Delivery"). Reaches past Flysystem straight to
 * the raw Aws\S3\S3Client, the same seam MultipartUploadGateway already
 * opened for the write side (Flysystem has no ranged-read API either), so
 * only the requested byte range is ever pulled from the bucket — never the
 * whole object, whichever range a resume asks for.
 *
 * `Storage::download()` was deliberately not used: it never looks at the
 * request's own `Range` header, so a resumed transfer through it would
 * silently restart from byte zero rather than resume — see ByteRange's own
 * doc comment.
 *
 * The route this action sits behind (routes/portal.php) requires both a
 * `signed` 24-hour URL and `gallery.gate:download`, and the response never
 * carries $zipBuildPart->key (the raw storage path) or the bucket name in
 * any header or body — a guest's browser only ever sees this URL.
 */
class DownloadZipPartController extends Controller
{
    public function __construct(private readonly S3Client $client) {}

    public function __invoke(Request $request, Gallery $gallery, ZipBuildPart $zipBuildPart, string $guestId): Response
    {
        $zipBuild = $zipBuildPart->zipBuild;

        abort_unless($zipBuild->galleryVersion->gallery_id === $gallery->id, 404);
        abort_unless($zipBuild->status === ZipBuildStatus::Ready, 404);

        try {
            $range = ByteRange::parse($request->header('Range'), $zipBuildPart->byte_size);
        } catch (RangeNotSatisfiableException $e) {
            return response('', 416, [
                'Accept-Ranges' => 'bytes',
                'Content-Range' => "bytes */{$e->totalSize}",
            ]);
        }

        // One row per request, whether fresh or resumed — the audit trail.
        // What's bounded is DownloadEvent::requesterDayCount(), not this
        // row count: three ranged requests resuming the same download the
        // same day write three rows but count as one requester-day.
        app(RecordDownloadEvent::class)($zipBuild, $guestId, $request->ip());

        $result = $this->client->getObject(array_filter([
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $zipBuildPart->key,
            'Range' => $range?->forS3(),
        ]));

        $headers = [
            'Content-Type' => 'application/zip',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) ($range?->length() ?? $zipBuildPart->byte_size),
            'Content-Disposition' => 'attachment; filename="'.$this->filename($zipBuild, $zipBuildPart).'"',
        ];

        if ($range !== null) {
            $headers['Content-Range'] = $range->contentRange($zipBuildPart->byte_size);
        }

        return response()->stream(function () use ($result) {
            $body = $result['Body'];
            while (! $body->eof()) {
                echo $body->read(1024 * 1024);
                flush();
            }
        }, $range !== null ? 206 : 200, $headers);
    }

    /** Never $zipBuildPart->key — that's the raw storage path this whole controller exists to keep off the wire. */
    private function filename(ZipBuild $zipBuild, ZipBuildPart $zipBuildPart): string
    {
        return "{$zipBuild->public_id}-part-{$zipBuildPart->part_number}.zip";
    }
}
