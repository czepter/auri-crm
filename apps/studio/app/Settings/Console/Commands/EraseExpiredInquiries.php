<?php

namespace App\Settings\Console\Commands;

use App\Crm\Actions\EraseContactDsgvo;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Models\Contact;
use App\Payments\Models\Invoice;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #501: löscht Kontakte, deren sämtliche Projekte unbuchte, verlorene oder
 * abgelehnte Anfragen sind, die seit `retention_months` nicht angefasst wurden.
 * Rechnungen (§ 147 AO) bleiben: wer eine hat, direkt oder über ein Projekt, fällt heraus.
 */
class EraseExpiredInquiries extends Command
{
    protected $signature = 'retention:erase';

    protected $description = 'Löscht abgelaufene verlorene und abgelehnte Anfragen samt Kontakt, wenn das Studio eine Frist gesetzt hat.';

    public function handle(StudioSettingsResolver $settings, EraseContactDsgvo $erase): int
    {
        $months = $settings->retentionMonths();

        if ($months === null) {
            return self::SUCCESS;
        }

        $cutoff = now()->subMonths($months);
        $count = 0;

        Contact::query()
            ->whereNull('dsgvo_erased_at')
            ->whereHas('projects')
            ->whereDoesntHave('projects', fn ($project) => $project->where(fn ($p) => $p
                ->whereDoesntHave('lead', fn ($lead) => $lead
                    ->whereIn('outcome', [LeadOutcome::Lost, LeadOutcome::Declined])
                    ->where('updated_at', '<', $cutoff))
                ->orWhereHas('job')))
            ->whereNotIn('id', Invoice::query()->whereNotNull('contact_id')->select('contact_id'))
            ->whereNotIn('id', DB::table('project_contacts')
                ->whereIn('project_id', Invoice::query()->whereNotNull('project_id')->select('project_id'))
                ->select('contact_id'))
            ->each(function (Contact $contact) use ($erase, &$count) {
                $erase($contact);
                $count++;
            });

        if ($count > 0) {
            AuditLogger::record('retention.erased', context: ['count' => $count, 'months' => $months]);
        }

        $this->info("{$count} gelöscht.");

        return self::SUCCESS;
    }
}
