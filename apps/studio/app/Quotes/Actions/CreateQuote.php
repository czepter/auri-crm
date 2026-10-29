<?php

namespace App\Quotes\Actions;

use App\Catalog\Models\Package;
use App\Crm\Models\ClientCase;
use App\Models\StudioSettings;
use App\Models\User;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/** The only writer of new quotes. */
class CreateQuote
{
    public function __construct(
        private readonly AllocateDocumentNumber $allocate,
        private readonly ResolveQuoteRecipient $resolveRecipient,
        private readonly SeedQuoteFromPackage $seed,
    ) {}

    /**
     * No explicit $validUntil takes the tenant's default, read once here and
     * never again: a later change to the setting is not this quote's concern.
     */
    public function __invoke(
        ClientCase $case,
        ?User $actor = null,
        ?string $validUntil = null,
        ?Package $package = null,
        ?Quote $predecessor = null,
    ): Quote {
        $quote = DB::transaction(function () use ($case, $validUntil, $predecessor) {
            $allocation = ($this->allocate)(documentType: DocumentType::Quote);
            $recipient = ($this->resolveRecipient)($case);

            return $case->quotes()->create([
                'valid_until' => $validUntil ?? now()->addDays(StudioSettings::current()->quote_validity_days)->toDateString(),
                'is_addendum' => $predecessor !== null,
                'predecessor_id' => $predecessor?->id,
                'number' => $allocation['number'],
                'recipient_contact_id' => $recipient?->id,
                'recipient_street' => $recipient?->street,
                'recipient_postcode' => $recipient?->postcode,
                'recipient_city' => $recipient?->city,
            ]);
        });

        if ($package !== null) {
            ($this->seed)($quote, $package);
        }

        AuditLogger::record(
            event: 'quote.created',
            actor: $actor,
            context: ['project' => $case->public_id, ...($predecessor ? ['addendum_to' => $predecessor->public_id] : [])],
            subject: $quote->public_id,
            projectId: $case->id,
        );

        return $quote;
    }
}
