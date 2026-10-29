<?php

namespace App\Staff\Support;

use App\Contracts\Notifications\Studio\ContractSigned;
use App\Crm\Notifications\Studio\InquiryReceived;
use App\Crm\Notifications\Studio\LeadStalled;
use App\Crm\Notifications\Studio\TasksDue;
use App\Engagement\Notifications\Studio\ClientMessagePosted;
use App\Gallery\Notifications\AssetCommented;
use App\Gallery\Notifications\AssetFavorited;
use App\Payments\Notifications\Studio\InstallmentDueSoon;
use App\Payments\Notifications\Studio\InstallmentOverdue;
use App\Payments\Notifications\Studio\InstallmentPaid;
use App\Portal\Notifications\Studio\QuestionnaireSubmitted;
use App\Privacy\Notifications\Studio\DsgvoRequested;
use App\Quotes\Notifications\Studio\QuoteAccepted;

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
        'inquiryReceived' => InquiryReceived::class,
        'clientMessage' => ClientMessagePosted::class,
        'dsgvoRequested' => DsgvoRequested::class,
        'quoteAccepted' => QuoteAccepted::class,
        'contractSigned' => ContractSigned::class,
        'questionnaireSubmitted' => QuestionnaireSubmitted::class,
        'installmentPaid' => InstallmentPaid::class,
        'installmentDueSoon' => InstallmentDueSoon::class,
        'installmentOverdue' => InstallmentOverdue::class,
        'tasksDue' => TasksDue::class,
        'leadStalled' => LeadStalled::class,
        'assetCommented' => AssetCommented::class,
        'assetFavorited' => AssetFavorited::class,
    ];

    /**
     * The three scan events, and only them. They describe states that are
     * still true in the morning; the others happen at a moment, and a
     * second collecting channel beside the summary would be the same
     * function under another name.
     *
     * @var list<class-string>
     */
    public const BRIEFABLE = [
        TasksDue::class,
        LeadStalled::class,
        InstallmentOverdue::class,
    ];
}
