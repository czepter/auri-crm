<?php

namespace App\Payments\Console\Commands;

use App\Automation\Actions\DraftPaymentReminders;
use App\Payments\Actions\DetectOverdueInstallments;
use Illuminate\Console\Command;

/**
 * The scheduled entry point for App\Payments\Actions\DetectOverdueInstallments
 * — see routes/console.php for the daily schedule that runs this.
 */
class DetectOverdueInstallmentsCommand extends Command
{
    protected $signature = 'payments:detect-overdue-installments';

    protected $description = 'Flags every scheduled payment installment whose due date has passed, dispatches InstallmentOverdue once per installment and drafts the client reminders.';

    public function handle(DetectOverdueInstallments $detect, DraftPaymentReminders $remind): int
    {
        $count = $detect();
        $remind();

        $this->info("Flagged {$count} overdue installment(s).");

        return self::SUCCESS;
    }
}
