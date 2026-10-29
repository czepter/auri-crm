<?php

namespace App\Gallery\Support;

use App\Crm\Models\ClientCase;
use App\Documents\Contracts\MergeDataProvider;
use Illuminate\Database\Eloquent\Model;

class GalleryMergeData implements MergeDataProvider
{
    /**
     * C-17 (#253): the newest gallery of this project, with the two secrets
     * in clear, or empty strings — a template that names them on a project
     * without a gallery is asking about something that does not exist.
     *
     * The link is apps/gallery's own address (Gallery::publicUrl()) — the
     * guest viewer, not this app's own host, which no guest can open.
     *
     * @param  ClientCase|null  $case
     * @return array<string, array<string, string>>
     */
    public function blocks(?Model $case): array
    {
        $gallery = $case?->galleries()->latest('id')->first();

        return ['gallery' => [
            'title' => $gallery?->title ?? '',
            'link' => $gallery?->publicUrl() ?? '',
            'password' => $gallery?->view_password ?? '',
            'downloadPin' => $gallery?->download_pin ?? '',
            'expiresOn' => $gallery?->expires_at?->format('d.m.Y') ?? '',
        ]];
    }
}
