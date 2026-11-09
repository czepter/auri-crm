<?php

namespace App\Gallery\Actions;

use App\Crm\Models\Contact;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use Illuminate\Database\UniqueConstraintViolationException;

class SetAssetHidden
{
    public function __invoke(Gallery $gallery, Contact $contact, string $key, bool $hidden): void
    {
        $mark = ['gallery_id' => $gallery->id, 'asset_key' => $key, 'contact_id' => $contact->id];

        if (! $hidden) {
            AssetHide::query()->where($mark)->delete();

            return;
        }

        try {
            AssetHide::query()->firstOrCreate($mark);
        } catch (UniqueConstraintViolationException) {
            // Zwei Taps gleichzeitig; die Zeile steht.
        }
    }
}
