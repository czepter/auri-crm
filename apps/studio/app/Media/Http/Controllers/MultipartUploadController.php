<?php

namespace App\Media\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Actions\StartOrResumeMultipartUpload;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Jobs\ProcessIngestedAsset;
use App\Media\Models\MultipartUpload;
use App\Media\Services\MultipartUploadGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The app-proxied multipart ingest API (G-02): the browser never talks to
 * S3, only to these four actions. Admin-surface only — every route sits
 * behind the `mfa` group in routes/admin.php, the same as every other staff
 * action.
 */
class MultipartUploadController extends Controller
{
    public function __construct(
        private readonly StartOrResumeMultipartUpload $startOrResume,
        private readonly MultipartUploadGateway $gateway,
    ) {}

    /**
     * Start a fresh upload session, or hand back the still-open one for the
     * same fingerprint together with whatever parts the bucket already has —
     * the whole resume story lives in StartOrResumeMultipartUpload.
     */
    public function store(Request $request, Gallery $gallery): JsonResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            // The browser's own File.lastModified: milliseconds since the
            // epoch, never a storage path or anything else that would leak
            // where the bytes end up.
            'file_modified_at' => ['required', 'integer', 'min:0'],
            'content_type' => ['nullable', 'string', 'max:255'],
        ]);

        ['upload' => $upload, 'parts' => $parts] = ($this->startOrResume)(
            $gallery,
            $validated['filename'],
            $validated['file_size'],
            Carbon::createFromTimestampMs($validated['file_modified_at']),
            $validated['content_type'] ?? null,
        );

        return response()->json([
            'upload' => [
                'public_id' => $upload->public_id,
                'part_size' => $upload->part_size,
            ],
            'parts' => $parts,
        ]);
    }

    /**
     * Relays one chunk straight through to S3's UploadPart — see
     * MultipartUploadGateway::uploadPart for why $request->getContent(true)
     * (the request's own unread php://input resource) is what makes that a
     * stream rather than a buffer.
     */
    public function uploadPart(Request $request, MultipartUpload $multipartUpload, int $partNumber): JsonResponse
    {
        $this->authorize('manageMedia', $multipartUpload->gallery);

        abort_unless($multipartUpload->status === MultipartUploadStatus::InProgress, 409);
        abort_if($partNumber < 1 || $partNumber > 10_000, 422, 'Part number out of range.');

        $contentLength = (int) $request->header('Content-Length', '0');
        abort_if($contentLength <= 0, 411, 'Content-Length required.');

        $etag = $this->gateway->uploadPart(
            $multipartUpload->key,
            $multipartUpload->upload_id,
            $partNumber,
            $request->getContent(true),
            $contentLength,
        );

        return response()->json(['part_number' => $partNumber, 'etag' => $etag]);
    }

    /**
     * Assembles the finished object from whatever the bucket's own ListParts
     * reports — never from anything the browser claims it sent — and marks
     * the session done. Dispatching ProcessIngestedAsset (G-03) onto
     * media.ingest here, rather than from wherever handles the S3 event, is
     * what promotes the object G-02 just landed in quarantine into a gallery
     * asset — see App\Media\Actions\IngestCompletedUpload.
     */
    public function complete(MultipartUpload $multipartUpload): JsonResponse
    {
        $this->authorize('manageMedia', $multipartUpload->gallery);

        abort_unless($multipartUpload->status === MultipartUploadStatus::InProgress, 409);

        $this->gateway->complete($multipartUpload->key, $multipartUpload->upload_id);

        $multipartUpload->forceFill([
            'status' => MultipartUploadStatus::Completed,
            'completed_at' => now(),
        ])->save();

        ProcessIngestedAsset::dispatch($multipartUpload)->onQueue('media.ingest');

        return response()->json(['status' => $multipartUpload->status]);
    }

    /** An operator-cancelled upload (#51's "abort explicitly when the operator cancels"). */
    public function destroy(MultipartUpload $multipartUpload): JsonResponse
    {
        $this->authorize('manageMedia', $multipartUpload->gallery);

        abort_unless($multipartUpload->status === MultipartUploadStatus::InProgress, 409);

        $this->gateway->abort($multipartUpload->key, $multipartUpload->upload_id);

        $multipartUpload->forceFill([
            'status' => MultipartUploadStatus::Aborted,
            'aborted_at' => now(),
        ])->save();

        return response()->json(['status' => $multipartUpload->status]);
    }
}
