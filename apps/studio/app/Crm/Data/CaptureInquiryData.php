<?php

namespace App\Crm\Data;

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
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
        public string $project_name,
        public string $type,
        public LeadSource $source,
        public CaptureChannel $captured_via,
        public ?string $date,
        public ?string $inquiry_text,
        public ?string $referred_by_contact_id,
        /**
         * What "Sonstige" actually was. Only ever set beside the Other case
         * of its own field — Project and Lead clear it otherwise.
         */
        public ?string $type_other = null,
        public ?string $source_other = null,
        public ?string $captured_via_other = null,
        /**
         * C-09 (#244): one row per person — name, mail, phone — because
         * `contacts` holds the three in one row. A partner used to arrive as
         * a bare name and could never be reached; every further person is a
         * ProjectContact of their own, so each gets their own portal login
         * rather than sharing an identity.
         *
         * Defaulted, because the api's own lead write (I-03) and the public
         * form both capture a single person.
         *
         * @var list<array{name: string, email: ?string, phone: ?string}>
         */
        public array $people = [],
        /**
         * When the inquiry reached the studio. Absent, it is this moment —
         * the only case the form has to fill in is the one where it wasn't.
         */
        public ?string $captured_at = null,
        /**
         * C-25: die Felder des Projekttyps, nach ProjectField::$key. Geprüft
         * von WriteProjectFieldValues statt hier, damit ein Wert derselben
         * Definition gehorcht, welche Oberfläche ihn auch schreibt.
         *
         * @var array<string, mixed>
         */
        public array $fields = [],
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
            'people' => ['array'],
            'people.*.name' => ['required', 'string', 'max:255'],
            'people.*.email' => ['nullable', 'email', 'max:255'],
            'people.*.phone' => ['nullable', 'string', 'max:64'],
            'project_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::exists('project_types', 'key')->where('is_active', true)],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'captured_via' => ['required', Rule::enum(CaptureChannel::class)],
            'type_other' => ['nullable', 'string', 'max:120'],
            'source_other' => ['nullable', 'string', 'max:120'],
            'captured_via_other' => ['nullable', 'string', 'max:120'],

            // Nullable and never checked against existing bookings. The
            // availability warning is advisory by design — see
            // CheckDateAvailability.
            'date' => ['nullable', 'date_format:Y-m-d'],
            'inquiry_text' => ['nullable', 'string', 'max:10000'],

            // A day, a moment, or nothing. Never the future: an inquiry that
            // has not arrived yet is a typo, and it would sit at the head of
            // every funnel month until someone found it.
            'captured_at' => ['nullable', 'date', 'before_or_equal:now'],
            'fields' => ['array'],

            // The CUID2 of an existing contact, same shape as contact_id
            // above. Nullable always, including when the source is a
            // referral — see the migration.
            'referred_by_contact_id' => ['nullable', 'string', 'size:24'],
        ];
    }
}
