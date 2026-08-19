<?php

namespace Database\Seeders;

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Models\FormField;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Models\StudioSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Accounts used only by the Playwright suite. Never registered in
 * DatabaseSeeder — it is invoked explicitly by the e2e setup.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        // db:seed --class=E2eSeeder is directly invocable and bypasses
        // DatabaseSeeder's own guard — same reasoning as DatabaseSeeder,
        // duplicated here because this seeder is never called from there.
        if (app()->isProduction()) {
            return;
        }

        // The Playwright accounts below sign in past this seeder's own
        // wizard on purpose — E2eSeeder is idempotent fixture data, not a
        // first-run scenario, and no spec here exercises /einrichtung/studio.
        // The cache flush matters here too: StudioSettingsResolver reads
        // through a redis-backed cache this raw update does not bust (see
        // its own doc comment), and this seeder can run against a
        // dev-server process that already has the pre-onboarding row cached.
        StudioSettings::query()->update(['onboarding_completed_at' => now()]);
        Cache::store('redis')->flush();

        User::updateOrCreate(
            ['email' => 'e2e-basic@studio.test'],
            [
                'name' => 'E2E Basic',
                'password' => 'correct-horse',
                'role' => StaffRole::Owner,
            ],
        );

        User::updateOrCreate(
            ['email' => 'e2e-mfa@studio.test'],
            [
                'name' => 'E2E MFA',
                'password' => 'correct-horse',
                'role' => StaffRole::Owner,
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'mfa_confirmed_at' => now(),
                'mfa_recovery_codes' => [],
            ],
        );

        $this->seedOccupiedDate();
        $this->seedEditableRecords();
    }

    /**
     * One booked Saturday, so the capture journey has a real collision to warn
     * about. Owned by this seeder rather than borrowed from DemoCrmSeeder: an
     * e2e fixture that depends on demo data breaks the moment the demo cast is
     * edited for a screenshot.
     *
     * updateOrCreate on the project keeps the seeder idempotent — Playwright
     * runs it before every local suite against a database that is not reset.
     */
    private function seedOccupiedDate(): void
    {
        $project = Project::updateOrCreate(
            ['name' => 'Meier & Kowalski — Hochzeit'],
            ['type' => ProjectType::Wedding],
        );

        if ($project->wasRecentlyCreated) {
            $project->attachContact(
                Contact::create(['name' => 'Meier & Kowalski']),
                ProjectContactRole::Primary,
            );

            Lead::factory()->for($project)->create([
                'captured_by_id' => User::query()->firstOrFail()->id,
            ]);

            Shoot::create([
                'project_id' => $project->id,
                'kind' => ShootKind::Wedding,
                'date' => '2026-06-14',
                'venue_name' => 'Gut Basthorst',
                'venue_city' => 'Basthorst',
                'is_primary' => true,
            ]);
        }
    }

    /**
     * One of each record whose editor only exists once the record does: a
     * package, a questionnaire template with an instance on a project, and a
     * quote still in draft. Without them the screens render their empty state
     * and a pixel baseline of that empty state proves nothing about the
     * editor behind it — see
     * docs/superpowers/specs/2026-08-19-tailwind-only-migration-design.md.
     *
     * Named "E2E …" so a developer looking at their own database can tell at
     * a glance where the row came from.
     */
    private function seedEditableRecords(): void
    {
        $package = Package::firstOrCreate(['name' => 'E2E Paket']);

        if ($package->wasRecentlyCreated) {
            PackageItem::factory()->for($package)->create();
        }

        $template = FormTemplate::firstOrCreate(
            ['name' => 'E2E Fragebogen'],
            ['project_type' => ProjectType::Wedding],
        );

        if ($template->wasRecentlyCreated) {
            FormField::factory()->for($template)->create();
        }

        $project = Project::query()->where('name', 'Meier & Kowalski — Hochzeit')->first();

        if ($project === null) {
            return;
        }

        $instance = FormInstance::firstOrCreate([
            'project_id' => $project->id,
            'name' => 'E2E Fragebogen',
        ]);

        if ($instance->wasRecentlyCreated) {
            FormInstanceField::factory()->for($instance)->create();
        }

        $quote = Quote::firstOrCreate(
            ['project_id' => $project->id, 'status' => QuoteStatus::Draft],
        );

        if ($quote->wasRecentlyCreated) {
            QuoteItem::factory()->for($quote)->create();
        }
    }
}
