<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\ReplyToAssetComment;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetCommentReplyController extends Controller
{
    public function store(Request $request, Gallery $gallery, AssetComment $comment, ReplyToAssetComment $reply): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_unless($comment->galleryVersion->gallery_id === $gallery->id && $comment->user_id === null, 404);
        abort_unless($gallery->allows('comments', true), 422);

        $reply($gallery, $comment, auth('staff')->user(), $request->validate(['body' => ['required', 'string', 'max:2000']])['body']);

        return back();
    }
}
