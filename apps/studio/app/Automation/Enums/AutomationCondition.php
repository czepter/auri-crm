<?php

namespace App\Automation\Enums;

use App\Crm\Enums\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * E-15: the single state a rule checks before it sends.
 *
 * One condition per rule, from a fixed list. No expression editor and no
 * AND/OR: four rows cover what rules are actually built for today, and a
 * fifth case arrives as a fifth row.
 */
#[TypeScript]
enum AutomationCondition: string implements HasLabel
{
    case PaymentOpen = 'payment_open';
    case ContractUnsigned = 'contract_unsigned';
    case QuestionnaireOpen = 'questionnaire_open';
    case GalleryUnopened = 'gallery_unopened';

    public function label(): string
    {
        return match ($this) {
            self::PaymentOpen => 'Rate offen',
            self::ContractUnsigned => 'Vertrag nicht unterschrieben',
            self::QuestionnaireOpen => 'Fragebogen offen',
            self::GalleryUnopened => 'Galerie nicht geöffnet',
        };
    }
}
