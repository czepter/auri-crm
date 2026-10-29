<?php

namespace App\Documents\Actions;

use App\Documents\Models\DocumentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordDocumentView
{
    public function __invoke(Request $request, Model $document, string $via): DocumentView
    {
        return $document->views()->create([
            'via' => $via,
            'ip_hash' => $request->ip() === null ? null : substr(hash_hmac('sha256', $request->ip(), config('app.key')), 0, 16),
            'user_agent' => $request->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 512),
            'counts' => $request->session()->get('portal_preview') !== true,
            'viewed_at' => now(),
        ]);
    }
}
