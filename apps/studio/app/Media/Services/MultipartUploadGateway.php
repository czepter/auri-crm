<?php

namespace App\Media\Services;

use Aws\Middleware;
use Aws\S3\S3Client;
use Psr\Http\Message\RequestInterface;

/**
 * The one seam past Flysystem for multipart upload, mirroring
 * ReapIncompleteMultipartUploads' own justification: Flysystem has no
 * multipart-upload API, so this talks to the underlying Aws\S3\S3Client
 * directly, per "Flysystem plus the AWS SDK is the abstraction" (issue #50).
 * Nothing here names a storage vendor — S3Client is bound from the `s3`
 * disk's own config (App\Providers\AppServiceProvider), so any S3-compatible
 * backend that disk points at works unmodified.
 */
class MultipartUploadGateway
{
    public function __construct(private readonly S3Client $client) {}

    public function create(string $key, ?string $contentType): string
    {
        $result = $this->client->createMultipartUpload(array_filter([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $contentType,
        ]));

        return $result['UploadId'];
    }

    /**
     * The bucket's own record of what has landed for this upload — the
     * source of truth #51 requires a resume to trust over any bookkeeping
     * this application might otherwise have kept itself.
     *
     * @return list<array{part_number: int, size: int, etag: string}>
     */
    public function listParts(string $key, string $uploadId): array
    {
        $parts = [];

        foreach ($this->client->getPaginator('ListParts', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]) as $page) {
            foreach ($page['Parts'] ?? [] as $part) {
                $parts[] = [
                    'part_number' => $part['PartNumber'],
                    'size' => $part['Size'],
                    'etag' => $part['ETag'],
                ];
            }
        }

        return $parts;
    }

    /**
     * Streams $body straight through to S3's UploadPart, never buffering it.
     * $body is expected to be the request's own raw, non-seekable
     * php://input resource (Illuminate\Http\Request::getContent(true)) —
     * exactly what "without the whole file — or a whole chunk beyond a
     * bounded buffer — ever being held in memory" (#51) requires: Guzzle
     * streams straight from that resource to the socket rather than reading
     * it into a PHP string first.
     *
     * Signed unsigned-body (`v4-unsigned-body`) is what makes that possible.
     * SigV4 payload signing normally hashes the whole body up front and then
     * rewinds the stream to actually send it — impossible on a stream that
     * can never be rewound. S3 supports skipping that hash entirely
     * (`X-Amz-Content-Sha256: UNSIGNED-PAYLOAD`) for exactly this case; the
     * request is still authenticated by its SigV4 Authorization header, only
     * the payload hash is skipped.
     *
     * That header is set directly on the built request (an `appendBuild`
     * middleware, running before the signer reads it) rather than through
     * the SDK's own `v4-unsigned-body` signature option: S3Client resolves
     * `signature_version` at construction, which — per
     * Aws\AwsClient::addSignatureMiddleware() — marks it "configured" and
     * makes the per-command `@context` override that option is meant to use
     * a no-op. Setting the header the signer already knows to defer to
     * (SignatureV4::getPayload()'s own "Handle streaming operations" branch)
     * sidesteps that entirely.
     */
    public function uploadPart(string $key, string $uploadId, int $partNumber, mixed $body, int $contentLength): string
    {
        $command = $this->client->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'Body' => $body,
            'ContentLength' => $contentLength,
        ]);
        $command->getHandlerList()->appendBuild(
            Middleware::mapRequest(
                fn (RequestInterface $request) => $request->withHeader('X-Amz-Content-Sha256', 'UNSIGNED-PAYLOAD')
            ),
            'unsigned-payload'
        );

        $result = $this->client->execute($command);

        return $result['ETag'];
    }

    /**
     * Assembles the Parts list from ListParts rather than from anything the
     * browser sent — the same "bucket is the source of truth" rule
     * listParts() itself is built on, so a client that lied about an ETag,
     * or simply went quiet mid-upload, cannot complete an upload with parts
     * that never actually landed.
     */
    public function complete(string $key, string $uploadId): string
    {
        $parts = $this->listParts($key, $uploadId);

        $result = $this->client->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => array_map(
                    fn (array $part) => ['PartNumber' => $part['part_number'], 'ETag' => $part['etag']],
                    $parts
                ),
            ],
        ]);

        return $result['ETag'];
    }

    public function abort(string $key, string $uploadId): void
    {
        $this->client->abortMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }

    private function bucket(): string
    {
        return config('filesystems.disks.s3.bucket');
    }
}
