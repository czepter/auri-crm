<?php

namespace App\Gallery\Notifications;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use Illuminate\Notifications\Notification;

/**
 * G-12: a gallery guest left a comment on one asset. in_app only — this is
 * what "reaches the studio through the unread stream" means (issue #71's own
 * acceptance wording), not a mail per comment. The first notification in this
 * codebase to address a staff member about a business event rather than a
 * client — see NotificationSettingsController's own doc comment, written
 * before this one existed.
 */
class AssetCommented extends Notification
{
    public function __construct(
        private readonly Gallery $gallery,
        private readonly AssetComment $comment,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['in_app'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'gallery_public_id' => $this->gallery->public_id,
            'gallery_title' => $this->gallery->title,
            'asset_key' => $this->comment->asset_key,
            'comment_public_id' => $this->comment->public_id,
            'body' => $this->comment->body,
        ];
    }
}
