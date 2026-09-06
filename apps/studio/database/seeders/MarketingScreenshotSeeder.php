<?php

namespace Database\Seeders;

use App\Contracts\Models\Contract;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data for the auri-crm-site screenshot captures — written to be shown
 * publicly indefinitely, so it does not reuse DemoCrmSeeder's own cast (built
 * for mockup 02, not for the public internet).
 */
class MarketingScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $staff = User::query()->first() ?? User::factory()->create();

        foreach ([
            ['Anna Voss', ProjectType::Wedding, LeadStage::New, 'Hof Bergmann', 'Lüneburg'],
            ['Familie Reuter', ProjectType::Portrait, LeadStage::Contacted, null, 'Hamburg'],
            ['Jonas Klee', ProjectType::Portrait, LeadStage::QuoteSent, 'Studio', 'Hamburg'],
        ] as [$name, $type, $stage, $venue, $city]) {
            $project = Project::create(['name' => $name, 'type' => $type]);
            $project->attachContact(Contact::factory()->create(['name' => $name]), ProjectContactRole::Primary);

            Shoot::create([
                'project_id' => $project->id,
                'kind' => $type === ProjectType::Wedding ? ShootKind::Wedding : ShootKind::Portrait,
                'venue_name' => $venue,
                'venue_city' => $city,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            Lead::factory()->for($project)->atStage($stage)->create([
                'source' => LeadSource::Referral,
                'captured_by_id' => $staff->id,
            ]);
        }

        $this->seedBookedWedding($staff);
    }

    /** Melissa & Jan Ahrens: a booked wedding carried through to an accepted quote, a rendered contract and a payment plan. */
    private function seedBookedWedding(User $staff): void
    {
        $project = Project::create(['name' => 'Melissa & Jan Ahrens', 'type' => ProjectType::Wedding]);
        $project->attachContact(Contact::factory()->create(['name' => 'Melissa Ahrens']), ProjectContactRole::Primary);
        $project->attachContact(Contact::factory()->create(['name' => 'Jan Ahrens']), ProjectContactRole::Partner);

        Shoot::create([
            'project_id' => $project->id,
            'kind' => ShootKind::Wedding,
            'date' => '2026-10-17',
            'call_time' => '14:00',
            'venue_name' => 'Gut Basthorst',
            'venue_city' => 'Basthorst',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        Lead::factory()->for($project)->atStage(LeadStage::Booked)->create([
            'source' => LeadSource::Referral,
            'captured_by_id' => $staff->id,
        ]);

        // Shoots must exist before booking — BookProject only advances the
        // ones already on the project.
        $job = app(BookProject::class)($project, $staff);

        $quote = Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Accepted,
            'sent_at' => now()->subDays(35),
            'accepted_at' => now()->subDays(30),
            'total' => 440000,
            'net' => 440000,
        ]);
        $quote->items()->create([
            'title' => 'Hochzeitsreportage Ganztag',
            'description' => 'bis 14 Std. am Hochzeitstag, Bearbeitung, Online-Galerie',
            'quantity' => 1,
            'unit_price' => 440000,
        ]);

        Contract::factory()->for($project)->rendered()->create();

        // The deferred sum-matches-total constraint on payment_installments
        // only checks at COMMIT, so the plan and its installments have to
        // land in one transaction (same reasoning as CreatePaymentPlan).
        DB::transaction(function () use ($job) {
            $plan = PaymentPlan::factory()->for($job)->create(['total' => 440000]);

            $plan->installments()->create([
                'amount' => 132000,
                'due_on' => now()->subDays(20)->toDateString(),
                'status' => PaymentInstallmentStatus::Paid,
                'settled_via' => SettledVia::Transfer,
                'paid_on' => now()->subDays(18)->toDateString(),
                'sort_order' => 0,
            ]);
            $plan->installments()->create([
                'amount' => 308000,
                'due_on' => '2026-10-03',
                'sort_order' => 1,
            ]);
        });

        Gallery::factory()->for($project)->create(['title' => 'Melissa & Jan'])->publish();
    }
}
