<?php

namespace Database\Seeders;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\User;
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
}
