<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\AssetCommentReplied;
use App\Models\User;

class ReplyToAssetComment
{
    public function __invoke(Gallery $gallery, AssetComment $comment, User $author, string $body): AssetComment
    {
        $reply = AssetComment::create([
            'gallery_version_id' => $comment->gallery_version_id,
            'asset_key' => $comment->asset_key,
            'user_id' => $author->id,
            'body' => $body,
        ]);

        $contact = $comment->contact;

        if ($contact?->email !== null && $gallery->allows('comments', true)) {
            $contact->notify(new AssetCommentReplied($gallery, $reply));
        }

        return $reply;
    }
}
