<?php

namespace App\Concerns;

use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

/**
 * T-06: every outgoing notification mail this story names — ClientAccessLink
 * (A-02's magic link) and StaffInvited — leaves under the tenant's own
 * identity rather than MAIL_FROM_ADDRESS/_NAME, and carries the same legal
 * particulars the rendered contract PDF does.
 *
 * One trait, used by both, is the whole point: StudioSettingsResolver::
 * attributes() is the one place this and App\Contracts\Jobs\RenderContractJob
 * ::mergeData() both read legal_name/street/postcode/city/tax_number/vat_id
 * from — a second, separately-typed footer per notification is exactly the
 * drift the story's acceptance criterion rules out.
 */
trait AppliesStudioMailIdentity
{
    protected function appendStudioMailIdentity(MailMessage $message, StudioSettingsResolver $settings): MailMessage
    {
        $message->from($settings->mailFromAddress(), $settings->mailFromName());

        if ($replyTo = $settings->mailReplyTo()) {
            $message->replyTo($replyTo);
        }

        if ($bcc = $settings->mailBcc()) {
            $message->bcc($bcc);
        }

        if ($signature = $settings->mailSignature()) {
            $message->line($signature);
        }

        if ($footer = $this->studioMailFooter($settings)) {
            $message->line($footer);
        }

        return $message;
    }

    private function studioMailFooter(StudioSettingsResolver $settings): ?string
    {
        $studio = $settings->attributes();

        $line = Collection::make([
            $studio['legal_name'] ?: $studio['display_name'],
            Collection::make([$studio['street'], trim(($studio['postcode'] ?? '').' '.($studio['city'] ?? ''))])
                ->filter()->implode(', '),
            Collection::make([
                $studio['tax_number'] ? "St.-Nr. {$studio['tax_number']}" : null,
                $studio['vat_id'] ? "USt-IdNr. {$studio['vat_id']}" : null,
            ])->filter()->implode(' · '),
        ])->filter()->implode(' · ');

        return $line !== '' ? $line : null;
    }
}
