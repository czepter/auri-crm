<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the inquiry reached the studio — a different question from LeadSource,
 * which is how the client heard about it. A referral can arrive by phone or by
 * mail, and neither fact can be recovered afterwards, so both are stored.
 */
#[TypeScript]
enum CaptureChannel: string implements HasLabel
{
    case Phone = 'phone';
    case Email = 'email';
    case InPerson = 'in_person';
    case WebForm = 'web_form';
    case Portal = 'portal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Telefonisch',
            self::Email => 'Per E-Mail',
            self::InPerson => 'Persönlich',
            self::WebForm => 'Über die Website',
            self::Portal => 'Über das Portal',
            self::Other => 'Sonstige',
        };
    }

    /**
     * C-08: the two channels a human at the studio never selects. The public
     * form sets WebForm and a client's own request out of the portal sets
     * Portal; nothing else may — otherwise "how many inquiries did the website
     * bring us" stops being answerable the first time someone picks it by hand
     * for a phone call, and the same goes for what aftersales brought in.
     */
    public static function staffSelectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $case !== self::WebForm && $case !== self::Portal,
        ));
    }
}
