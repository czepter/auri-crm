<?php

namespace App\Notifications\Support;

use App\Gallery\Notifications\AssetCommented;
use App\Gallery\Notifications\AssetFavorited;
use App\Notifications\Studio;

/**
 * The rows on the profile screen's Benachrichtigungen pane, and the only
 * list of them. The key is what the form field is called and what de.json
 * translates; the class is what a preference row is keyed on.
 *
 * Adding a staff-facing notification means adding a line here — the pane,
 * the validator and the digest all read this, so none of them can drift
 * from the others.
 */
final class StudioEvents
{
    /** @var array<string, class-string> */
    public const ALL = [
        'inquiryReceived' => Studio\InquiryReceived::class,
        'clientMessage' => Studio\ClientMessagePosted::class,
        'quoteAccepted' => Studio\QuoteAccepted::class,
        'contractSigned' => Studio\ContractSigned::class,
        'questionnaireSubmitted' => Studio\QuestionnaireSubmitted::class,
        'installmentPaid' => Studio\InstallmentPaid::class,
        'installmentDueSoon' => Studio\InstallmentDueSoon::class,
        'installmentOverdue' => Studio\InstallmentOverdue::class,
        'tasksDue' => Studio\TasksDue::class,
        'leadStalled' => Studio\LeadStalled::class,
        'assetCommented' => AssetCommented::class,
        'assetFavorited' => AssetFavorited::class,
    ];
}
