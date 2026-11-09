<?php

namespace App\Automation\Enums;

use App\Crm\Enums\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * E-15: the single state a rule checks before it sends.
 *
 * One condition per rule, from a fixed list. No expression editor and no
 * AND/OR: three rows cover what rules are actually built for today, and a
 * fourth case arrives as a fourth row.
 */
#[TypeScript]
enum AutomationCondition: string implements HasLabel
{
    case PaymentOpen = 'payment_open';
    case QuestionnaireOpen = 'questionnaire_open';
    case GalleryUnopened = 'gallery_unopened';

    public function label(): string
    {
        return match ($this) {
            self::PaymentOpen => 'Rate offen',
            self::QuestionnaireOpen => 'Fragebogen offen',
            self::GalleryUnopened => 'Galerie nicht geöffnet',
        };
    }

    /** After "wenn ". */
    public function clause(): string
    {
        return __("settings.condition_clauses.{$this->value}");
    }
}
