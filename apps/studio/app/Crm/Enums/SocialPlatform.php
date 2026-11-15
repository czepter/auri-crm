<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SocialPlatform: string implements HasLabel
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Pinterest = 'pinterest';
    case LinkedIn = 'linkedin';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::Pinterest => 'Pinterest',
            self::LinkedIn => 'LinkedIn',
            self::Website => 'Website',
        };
    }
}
