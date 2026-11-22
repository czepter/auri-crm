<?php

namespace App\Finance\Actions;

use App\Finance\Models\FinanceDocument;
use App\Models\User;
use App\Support\AuditLogger;
use finfo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A receipt goes onto the `s3` disk under a key the server makes up; what the
 * client calls the file is stored as data and nothing more. The type comes from
 * sniffing the bytes, not from the name or the declared mime. The same bytes
 * twice give back the first document.
 */
class StoreFinanceDocument
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /** The last segment of a path in either separator, free of control characters; the name is data and ends up in a header. */
    public static function cleanName(string $name): string
    {
        $parts = preg_split('#[\\\\/]#', preg_replace('/[\x00-\x1F\x7F]/', '', $name));
        $last = (string) end($parts);

        return mb_substr($last === '' ? 'beleg' : $last, 0, 255);
    }

    /**
     * What the bytes at $path are: sniffed type, size and hash. Refuses what is
     * not a receipt.
     *
     * @return array{mime: string, size: int, sha256: string}
     */
    public function inspect(string $path): array
    {
        $size = (int) filesize($path);
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($size === 0 || ! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['file' => __('finance.unsupportedFile')]);
        }

        if ($size > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => __('finance.tooLarge')]);
        }

        return ['mime' => $mime, 'size' => $size, 'sha256' => hash_file('sha256', $path)];
    }

    /** @return array{0: FinanceDocument, 1: bool} the document and whether it was already held */
    public function __invoke(UploadedFile $file, ?User $actor = null): array
    {
        $path = $file->getRealPath();
        ['mime' => $mime, 'size' => $size, 'sha256' => $hash] = $this->inspect($path);

        if ($existing = FinanceDocument::query()->where('sha256', $hash)->first()) {
            return [$existing, true];
        }

        try {
            $document = DB::transaction(function () use ($file, $path, $size, $mime, $hash, $actor) {
                $document = FinanceDocument::create([
                    'filename' => self::cleanName($file->getClientOriginalName()),
                    'mime' => $mime,
                    'size' => $size,
                    'sha256' => $hash,
                    'uploaded_at' => now(),
                    'created_by' => $actor?->id,
                ]);

                Storage::disk('s3')->put($document->storageKey(), fopen($path, 'r'));

                return $document;
            });
        } catch (UniqueConstraintViolationException) {
            return [FinanceDocument::query()->where('sha256', $hash)->firstOrFail(), true];
        }

        AuditLogger::record('finance.document.uploaded', $actor, ['size' => $size, 'mime' => $document->mime], $document->public_id);

        return [$document, false];
    }
}
