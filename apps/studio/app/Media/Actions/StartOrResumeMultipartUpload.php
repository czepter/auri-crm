<?php

namespace App\Media\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Console\Commands\ReapIncompleteMultipartUploads;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\MultipartUpload;
use App\Media\Services\MultipartUploadGateway;
use Aws\Exception\AwsException;
use Carbon\CarbonInterface;
use Visus\Cuid2\Cuid2;

/**
 * The find-or-create half of G-02's resume story. A returning tab supplies
 * the same fingerprint (filename, file_size, file_modified_at) it started
 * with — never a storage key or uploadId, which it was never given — and
 * this either hands back the still-open session underneath it, parts and
 * all, or starts a fresh one.
 *
 * Fixed part size, chosen once and recorded on the row: a resumed upload
 * that re-chunked at a different size would produce parts
 * CompleteMultipartUpload can never reconcile (#51).
 */
class StartOrResumeMultipartUpload
{
    /** 8 MiB — above S3's 5 MiB minimum part size, and comfortably inside the 10,000-part ceiling for the 30 GB files this story sizes for (~3,600 parts). */
    public const PART_SIZE = 8 * 1024 * 1024;

    public function __construct(private readonly MultipartUploadGateway $gateway) {}

    /** @return array{upload: MultipartUpload, parts: list<array{part_number: int, size: int, etag: string}>} */
    public function __invoke(
        Gallery $gallery,
        GallerySet $set,
        string $filename,
        int $fileSize,
        CarbonInterface $fileModifiedAt,
        ?string $contentType,
    ): array {
        // Fingerprint excludes the set on purpose: the same file re-picked
        // after a reload is the same upload even if a different set is open
        // now. A resumed row keeps the set it was started against — bytes
        // already in flight for one set don't silently retarget to whatever
        // fach happens to be open on this later request.
        $existing = MultipartUpload::query()
            ->where('gallery_id', $gallery->id)
            ->where('filename', $filename)
            ->where('file_size', $fileSize)
            ->where('file_modified_at', $fileModifiedAt)
            ->where('status', MultipartUploadStatus::InProgress)
            ->first();

        if ($existing !== null) {
            try {
                return ['upload' => $existing, 'parts' => $this->gateway->listParts($existing->key, $existing->upload_id)];
            } catch (AwsException) {
                // The bucket no longer knows this uploadId — the reaper (or
                // an operator) aborted it since this row was created. Retire
                // the stale row and fall through to a fresh session rather
                // than handing the browser one it can never complete.
                $existing->forceFill(['status' => MultipartUploadStatus::Aborted, 'aborted_at' => now()])->save();
            }
        }

        $publicId = (string) new Cuid2;
        $key = ReapIncompleteMultipartUploads::QUARANTINE_PREFIX.$publicId.'/'.$filename;
        $uploadId = $this->gateway->create($key, $contentType);

        $upload = MultipartUpload::create([
            'public_id' => $publicId,
            'gallery_id' => $gallery->id,
            'gallery_set_id' => $set->id,
            'key' => $key,
            'upload_id' => $uploadId,
            'filename' => $filename,
            'file_size' => $fileSize,
            'file_modified_at' => $fileModifiedAt,
            'part_size' => self::PART_SIZE,
            'status' => MultipartUploadStatus::InProgress,
        ]);

        return ['upload' => $upload, 'parts' => []];
    }
}
