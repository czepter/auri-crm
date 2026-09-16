<?php

namespace App\Automation\Enums;

use App\Crm\Enums\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The hard part E-08 exists for. PerShoot: "seven days before the shoot" is
 * a different date per shoot on a multi-shoot project — the sweep checks
 * every shoot's own date and fires against that Shoot. PrimaryShoot: "after
 * the wedding" means the one shoot flagged is_primary, and fires against its
 * Project rather than the Shoot itself — see AutomationRun's own docblock.
 *
 * Only set on a date-offset rule; null on an event-triggered one, whose
 * anchor is whichever Project or Shoot the firing event itself carries.
 */
#[TypeScript]
enum AutomationAnchor: string implements HasLabel
{
    case PerShoot = 'per_shoot';
    case PrimaryShoot = 'primary_shoot';

    /**
     * The date a sent quote stops being valid. Fires against the quote's
     * Project rather than the Quote: Q-06 makes a revision a new row, and a
     * nudge trail keyed to the row would forget every reminder the moment the
     * studio revises the offer.
     */
    case QuoteValidity = 'quote_validity';

    /**
     * E-16: three more dates the machinery could already carry and did not
     * see. The offset works in both directions, so `installment_due` is the
     * announcement that prevents T-22's arrears rather than the repair after
     * them — and it belongs here and not in the overdue scan, because it is
     * a studio's decision and not a system truth.
     */
    case InstallmentDue = 'installment_due';
    case GalleryExpiry = 'gallery_expiry';
    case QuestionnaireDue = 'questionnaire_due';

    public function label(): string
    {
        return match ($this) {
            self::PerShoot => 'Jedes Shooting',
            self::PrimaryShoot => 'Haupttermin',
            self::QuoteValidity => 'Angebot gültig bis',
            self::InstallmentDue => 'Rate fällig',
            self::GalleryExpiry => 'Galerie läuft ab',
            self::QuestionnaireDue => 'Kundenaufgabe fällig',
        };
    }
}
