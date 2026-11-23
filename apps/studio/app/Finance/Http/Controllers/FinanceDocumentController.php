<?php

namespace App\Finance\Http\Controllers;

use App\Concerns\AuthorizesFinances;
use App\Finance\Actions\StoreFinanceDocument;
use App\Finance\Data\FinanceDocumentData;
use App\Finance\Models\FinanceDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceDocumentController extends Controller
{
    use AuthorizesFinances;

    public function store(Request $request, StoreFinanceDocument $store): JsonResponse
    {
        $this->authorizeFinances();

        $request->validate(['file' => ['required', 'file']]);
        [$document, $existing] = $store($request->file('file'), $request->user('staff'));

        return response()->json([
            'document' => FinanceDocumentData::fromModel($document),
            'duplicateOfExpenseId' => $existing ? $document->expenses()->first()?->public_id : null,
        ], $existing ? 200 : 201);
    }

    /** Bytes leave only through here: no key, bucket or presigned URL reaches the browser. */
    public function show(FinanceDocument $document): StreamedResponse
    {
        $this->authorizeFinances();

        $disk = Storage::disk('s3');
        abort_unless($document->uploaded_at !== null && $disk->exists($document->storageKey()), 404);

        return $disk->response($document->storageKey(), $document->filename, [
            'Content-Type' => $document->mime,
            // Chromium's PDF viewer will not start under `sandbox`; a PDF carries no script or sub-resource of ours, so it is only kept from being framed elsewhere.
            'Content-Security-Policy' => $document->mime === 'application/pdf' ? "frame-ancestors 'self'" : 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
