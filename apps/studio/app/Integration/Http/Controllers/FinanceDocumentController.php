<?php

namespace App\Integration\Http\Controllers;

use App\Finance\Actions\StoreFinanceDocument;
use App\Finance\Models\FinanceDocument;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A receipt arrives in two steps: an authenticated request announces it and
 * gets a signed address, and the bytes are PUT there. The address is the only
 * credential the second step has, so it expires, fills one row once and is
 * throttled.
 */
class FinanceDocumentController extends Controller
{
    private const VALID_MINUTES = 15;

    public function uploads(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['required', Rule::in(StoreFinanceDocument::MIMES)],
            'size' => ['required', 'integer', 'between:1,'.StoreFinanceDocument::MAX_BYTES],
            'sha256' => ['nullable', 'regex:/^[0-9a-f]{64}$/'],
            'source_ref' => ['nullable', 'array'],
        ]);

        if (isset($data['sha256']) && $existing = FinanceDocument::query()->where('sha256', $data['sha256'])->first()) {
            return response()->json(['documentId' => $existing->public_id, 'uploadUrl' => null, 'expiresAt' => null, 'duplicate' => true]);
        }

        $principal = $request->user('api');
        $document = FinanceDocument::create([
            'filename' => StoreFinanceDocument::cleanName($data['filename']),
            'mime' => $data['mime'],
            'size' => $data['size'],
            'source_ref' => $data['source_ref'] ?? null,
            'created_by' => $principal instanceof User ? $principal->id : null,
        ]);

        // The caller reaches this app on its public address, not on the host of the sub-request a tool call makes.
        $expires = now()->addMinutes(self::VALID_MINUTES);
        URL::forceRootUrl(config('app.url'));

        try {
            $url = URL::temporarySignedRoute('api.finance.documents.content', $expires, ['document' => $document->public_id]);
        } finally {
            URL::forceRootUrl(null);
        }

        return response()->json([
            'documentId' => $document->public_id,
            'uploadUrl' => $url,
            'expiresAt' => $expires->toIso8601String(),
            'duplicate' => false,
        ], 201);
    }

    public function content(Request $request, FinanceDocument $document, StoreFinanceDocument $store): JsonResponse
    {
        if ($document->uploaded_at !== null) {
            return response()->json(['message' => 'This upload already holds a file.', 'document_id' => $document->public_id], 409);
        }

        if ((int) $request->header('Content-Length') > StoreFinanceDocument::MAX_BYTES) {
            return response()->json(['message' => __('finance.tooLarge')], 413);
        }

        $path = tempnam(sys_get_temp_dir(), 'finance');

        try {
            $out = fopen($path, 'wb');
            $copied = stream_copy_to_stream($request->getContent(true), $out, StoreFinanceDocument::MAX_BYTES + 1);
            fclose($out);

            if ($copied > StoreFinanceDocument::MAX_BYTES) {
                return response()->json(['message' => __('finance.tooLarge')], 413);
            }

            try {
                $file = $store->inspect($path);
            } catch (ValidationException $e) {
                // The caller of a signed address may not ask for JSON, and a redirect is no answer here.
                return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
            }

            $other = FinanceDocument::query()->where('sha256', $file['sha256'])->whereKeyNot($document->id)->first();

            if ($other !== null) {
                return response()->json(['message' => 'The tenant already holds this file.', 'document_id' => $other->public_id], 409);
            }

            // Claim the row first: of two concurrent puts on one address only one gets to write.
            try {
                $claimed = FinanceDocument::query()->whereKey($document->id)->whereNull('uploaded_at')->update([...$file, 'uploaded_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                return response()->json(['message' => 'The tenant already holds this file.'], 409);
            }

            if ($claimed === 0) {
                return response()->json(['message' => 'This upload already holds a file.', 'document_id' => $document->public_id], 409);
            }

            try {
                Storage::disk('s3')->put($document->storageKey(), fopen($path, 'r'));
            } catch (Throwable $e) {
                FinanceDocument::query()->whereKey($document->id)->update(['uploaded_at' => null, 'sha256' => null]);

                throw $e;
            }
        } finally {
            @unlink($path);
        }

        AuditLogger::record('finance.document.uploaded', null, ['size' => $file['size'], 'mime' => $file['mime']], $document->public_id);

        return response()->json(['documentId' => $document->public_id, 'sha256' => $file['sha256'], 'size' => $file['size']]);
    }
}
