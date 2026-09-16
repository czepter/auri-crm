<?php

namespace App\Concerns;

use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * T-06: every outgoing notification mail this story names — ClientAccessLink
 * (A-02's magic link) and StaffInvited — leaves under the tenant's own
 * identity rather than MAIL_FROM_ADDRESS/_NAME, and carries the same legal
 * particulars a rendered PDF does.
 *
 * One trait, used by both, is the whole point: StudioSettingsResolver::
 * attributes() is the one place this and DocumentMergeDataBuilder both read
 * legal_name/street/postcode/city/tax_number/vat_id from — a second, separately-typed footer per notification is exactly the
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

        return $message;
    }
}
