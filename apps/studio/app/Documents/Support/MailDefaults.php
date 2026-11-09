<?php

namespace App\Documents\Support;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Models\StudioSettings;

/** The mail template a studio chose as the standard for an occasion, resolved against that occasion's data. */
final class MailDefaults
{
    public const OCCASIONS = ['quote_sent', 'quote_follow_up', 'invoice_sent', 'payment_reminder', 'credit_note_sent'];

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: ?string, message: string}|null
     */
    public static function resolve(string $occasion, array $data): ?array
    {
        $family = StudioSettings::current()->mail_defaults[$occasion] ?? null;

        $template = $family === null ? null : DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Mail)
            ->where('family_id', $family)
            ->first();

        if ($template === null) {
            return null;
        }

        return [
            'subject' => filled($template->subject) ? MergeFieldResolver::resolve($template->subject, $data) : null,
            'message' => PlainTextBody::fromHtml(MergeFieldResolver::resolve($template->body, $data)),
        ];
    }
}
