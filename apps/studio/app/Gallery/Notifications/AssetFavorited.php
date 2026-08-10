<?php

namespace App\Gallery\Notifications;

use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use Illuminate\Notifications\Notification;

/**
 * G-12: a gallery guest favorited one asset. in_app only, same reasoning as
 * AssetCommented's own doc comment. Dispatched only when a favorite is added
 * (AssetFavoriteController::toggle()'s `true` branch) — a guest un-marking
 * one is not itself an event the studio needs surfaced.
 */
class AssetFavorited extends Notification
{
    public function __construct(
        private readonly Gallery $gallery,
        private readonly AssetFavorite $favorite,
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
            'asset_key' => $this->favorite->asset_key,
        ];
    }
}
