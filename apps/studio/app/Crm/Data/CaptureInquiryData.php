<?php

namespace App\Crm\Data;

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\ProjectType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * The capture form's payload. Six fields the studio fills plus the contact
 * block, matching mockup 03 — venue, guest count, further shoots and package
 * are deliberately absent and belong to the project screen.
 *
 * No #[TypeScript]: this is request input, and the frontend builds it rather
 * than receiving it.
 */
class CaptureInquiryData extends Data
{
    public function __construct(
        public ?string $contact_id,
        public ?string $contact_name,
        public ?string $contact_email,
        public ?string $contact_phone,
        public ?string $partner_name,
        public string $project_name,
        public ProjectType $type,
        public LeadSource $source,
        public CaptureChannel $captured_via,
        public ?string $date,
        public ?string $inquiry_text,
        public ?string $referred_by_contact_id,
    ) {}

    public static function rules(): array
    {
        return [
            // The CUID2 of an existing contact, or a name for a new one. One
            // of the two is required; both together simply reuse the contact.
            'contact_id' => ['nullable', 'string', 'size:24'],
            'contact_name' => ['nullable', 'string', 'max:255', 'required_without:contact_id'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'project_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'captured_via' => ['required', Rule::enum(CaptureChannel::class)],

            // Nullable and never checked against existing bookings. The
            // availability warning is advisory by design — see
            // CheckDateAvailability.
            'date' => ['nullable', 'date_format:Y-m-d'],
            'inquiry_text' => ['nullable', 'string', 'max:10000'],

            // The CUID2 of an existing contact, same shape as contact_id
            // above. Nullable always, including when the source is a
            // referral — see the migration.
            'referred_by_contact_id' => ['nullable', 'string', 'size:24'],
        ];
    }
}
