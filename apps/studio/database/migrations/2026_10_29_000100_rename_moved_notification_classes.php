<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #563, #560 (PaymentReminder): both columns store a notification's class name, and the class moved.
 * Left alone, a preference falls back to its default and the digest no
 * longer finds the in-app rows it collects.
 */
return new class extends Migration
{
    private const MOVED = [
        'App\Notifications\Studio\InquiryReceived' => 'App\Crm\Notifications\Studio\InquiryReceived',
        'App\Notifications\Studio\LeadStalled' => 'App\Crm\Notifications\Studio\LeadStalled',
        'App\Notifications\Studio\TasksDue' => 'App\Crm\Notifications\Studio\TasksDue',
        'App\Notifications\Studio\InstallmentDueSoon' => 'App\Payments\Notifications\Studio\InstallmentDueSoon',
        'App\Notifications\Studio\InstallmentOverdue' => 'App\Payments\Notifications\Studio\InstallmentOverdue',
        'App\Notifications\Studio\InstallmentPaid' => 'App\Payments\Notifications\Studio\InstallmentPaid',
        'App\Notifications\Studio\QuoteAccepted' => 'App\Quotes\Notifications\Studio\QuoteAccepted',
        'App\Notifications\Studio\ContractSigned' => 'App\Contracts\Notifications\Studio\ContractSigned',
        'App\Notifications\Studio\ClientMessagePosted' => 'App\Engagement\Notifications\Studio\ClientMessagePosted',
        'App\Notifications\Studio\QuestionnaireSubmitted' => 'App\Portal\Notifications\Studio\QuestionnaireSubmitted',
        'App\Notifications\Studio\DsgvoRequested' => 'App\Privacy\Notifications\Studio\DsgvoRequested',
        'App\Notifications\Studio\StudioDeletionCode' => 'App\Privacy\Notifications\Studio\StudioDeletionCode',
        'App\Notifications\Studio\StudioDeletionReminder' => 'App\Privacy\Notifications\Studio\StudioDeletionReminder',
        'App\Notifications\StaffInvited' => 'App\Staff\Notifications\StaffInvited',
        'App\Notifications\StaffEmailChangeConfirmation' => 'App\Staff\Notifications\StaffEmailChangeConfirmation',
        'App\Notifications\StaffOwnershipTransferred' => 'App\Staff\Notifications\StaffOwnershipTransferred',
        'App\Notifications\Studio\StudioWelcome' => 'App\Staff\Notifications\StudioWelcome',
        'App\Notifications\Studio\StudioReady' => 'App\Staff\Notifications\StudioReady',
        'App\Notifications\Studio\StudioSetupPending' => 'App\Staff\Notifications\StudioSetupPending',
        'App\Notifications\Studio\StudioEmailVerification' => 'App\Staff\Notifications\StudioEmailVerification',
        'App\Notifications\Studio\MorningBriefing' => 'App\Staff\Notifications\MorningBriefing',
        'App\Notifications\Studio\StudioDigest' => 'App\Staff\Notifications\StudioDigest',
        'App\Portal\Notifications\PaymentReminder' => 'App\Payments\Notifications\PaymentReminder',
    ];

    public function up(): void
    {
        $this->rename(self::MOVED);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::MOVED));
    }

    /** @param  array<string, string>  $classes */
    private function rename(array $classes): void
    {
        foreach ($classes as $from => $to) {
            DB::table('notification_preferences')->where('notification_class', $from)->update(['notification_class' => $to]);
            DB::table('in_app_notifications')->where('type', $from)->update(['type' => $to]);
        }
    }
};
