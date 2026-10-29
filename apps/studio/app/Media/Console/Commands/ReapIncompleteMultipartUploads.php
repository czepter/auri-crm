<?php

namespace App\Media\Console\Commands;

use App\Media\Models\MultipartUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * G-01a's one lifecycle rule (issue #50): nothing on the bucket expires on a
 * timer except this. An abandoned multipart upload is billed storage no
 * listing shows, so anything left incomplete under the quarantine prefix
 * past the age threshold gets aborted.
 *
 * G-02 (#51) is what will actually start a multipart upload under
 * QUARANTINE_PREFIX; this command is the cleanup half of that seam, shipped
 * ahead of the upload flow itself.
 *
 * Talks to the `s3` disk's underlying Aws\S3\S3Client directly — Flysystem
 * has no multipart-listing API of its own, and this is the one place the
 * app reaches past it, per the "Flysystem plus the AWS SDK is the
 * abstraction" rule in #50. Nothing here names a storage vendor.
 */
class ReapIncompleteMultipartUploads extends Command
{
    protected $signature = 'media:reap-multipart-uploads
        {--older-than=24 : Hours since a part was last uploaded before its upload is aborted}';

    protected $description = 'Abort incomplete multipart uploads under the quarantine prefix past the age threshold';

    public function handle(): int
    {
        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');
        $cutoff = now()->subHours((int) $this->option('older-than'));

        $reaped = 0;

        // No server-side `Prefix` here on purpose: found while smoke-testing
        // this against MinIO — ListMultipartUploads silently ignored it and
        // returned zero results for a prefix that plainly matched, on a
        // request that returned every upload correctly once Prefix was
        // dropped. Exactly the kind of S3-feature-set inconsistency #50 warns
        // support varies across S3-compatible backends, so this lists
        // everything and filters client-side instead of trusting a
        // server-side filter this story cannot verify holds on every
        // provider the disk has to run on.
        $pages = $client->getPaginator('ListMultipartUploads', [
            'Bucket' => $bucket,
        ]);

        foreach ($pages as $page) {
            foreach ($page['Uploads'] ?? [] as $upload) {
                if (! str_starts_with($upload['Key'], MultipartUpload::QUARANTINE_PREFIX)) {
                    continue;
                }

                if ($upload['Initiated']->getTimestamp() > $cutoff->getTimestamp()) {
                    continue;
                }

                $client->abortMultipartUpload([
                    'Bucket' => $bucket,
                    'Key' => $upload['Key'],
                    'UploadId' => $upload['UploadId'],
                ]);

                $reaped++;
            }
        }

        $this->info("Aborted {$reaped} incomplete multipart upload(s) under ".MultipartUpload::QUARANTINE_PREFIX);

        return self::SUCCESS;
    }
}
