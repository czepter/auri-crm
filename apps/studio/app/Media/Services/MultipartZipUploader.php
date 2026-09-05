<?php

namespace App\Media\Services;

/**
 * The write side of one ZIP archive volume's multipart upload (G-10, #59).
 * ZipStream's own output stream (a ZipStream\Stream\CallbackStreamWrapper)
 * calls write() with every chunk it produces; this buffers those chunks and
 * fires a fixed 16MiB UploadPart via App\Media\Services\MultipartUploadGateway
 * once enough has accumulated — never the whole archive, so peak memory
 * stays near 16MiB regardless of the volume's total size. Fixed at 16MiB
 * (not S3's 5MiB minimum) to keep part count under S3's 10,000-part ceiling
 * at the 3.9GiB split size (~250 parts).
 */
class MultipartZipUploader
{
    public const PART_SIZE = 16 * 1024 * 1024;

    private string $buffer = '';

    private int $partNumber = 0;

    private int $totalBytes = 0;

    private readonly string $uploadId;

    public function __construct(
        private readonly MultipartUploadGateway $gateway,
        private readonly string $key,
    ) {
        $this->uploadId = $this->gateway->create($this->key, 'application/zip');
    }

    public function write(string $bytes): void
    {
        $this->buffer .= $bytes;
        $this->totalBytes += strlen($bytes);

        while (strlen($this->buffer) >= self::PART_SIZE) {
            $this->uploadPart(substr($this->buffer, 0, self::PART_SIZE));
            $this->buffer = substr($this->buffer, self::PART_SIZE);
        }
    }

    /** Flushes whatever's left as the final (possibly <16MiB) part and completes the upload. */
    public function complete(): void
    {
        if ($this->buffer !== '') {
            $this->uploadPart($this->buffer);
            $this->buffer = '';
        }

        $this->gateway->complete($this->key, $this->uploadId);
    }

    public function abort(): void
    {
        $this->gateway->abort($this->key, $this->uploadId);
    }

    /** Total bytes written to this volume — the resulting S3 object's own size. */
    public function totalBytes(): int
    {
        return $this->totalBytes;
    }

    private function uploadPart(string $chunk): void
    {
        $this->partNumber++;
        $this->gateway->uploadPart($this->key, $this->uploadId, $this->partNumber, $chunk, strlen($chunk));
    }
}
