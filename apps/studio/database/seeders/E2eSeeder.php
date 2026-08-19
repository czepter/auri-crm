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
use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\StartThread;
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
        // T-08's four client switches default to off, and a screen behind an
        // off switch answers 404 — so the mailbox and the payment screen have
        // no frame in any database that has not turned them on.
        StudioSettings::query()->update([
            'onboarding_completed_at' => now(),
            'portal_messaging_mode' => 'any',
            'portal_accept_quotes_enabled' => true,
            'portal_sign_contracts_enabled' => true,
            'portal_payment_status_enabled' => true,
        ]);
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
            // Visible in the portal, and with nothing reserved on it: that is
            // the state ProjectController renders Projects/Inquiry for, and
            // there is otherwise no database in which that screen has a frame.
            ['type' => ProjectType::Wedding, 'portal_visible' => true],
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

        // Guarded against mass assignment on purpose — sharing a questionnaire
        // is a decision, not a form field. Set directly, and only once.
        if ($instance->shared_at === null) {
            $instance->shared_at = now();
            $instance->save();
        }

        if ($instance->wasRecentlyCreated) {
            FormInstanceField::factory()->for($instance)->create();
        }

        $quote = Quote::firstOrCreate(
            ['project_id' => $project->id, 'status' => QuoteStatus::Draft],
        );

        if ($quote->wasRecentlyCreated) {
            QuoteItem::factory()->for($quote)->create();
        }

        $this->seedThread($project);
    }

    /**
     * One conversation with a reply on it. The mailbox renders its empty
     * state without one, and an empty state is not the screen — the thread
     * list, the conversation and both sides of a message only exist here.
     */
    private function seedThread(Project $project): void
    {
        $contact = $project->contacts()->first();

        if ($contact === null || $contact->threads()->where('subject', 'E2E Faden')->exists()) {
            return;
        }

        $thread = app(StartThread::class)($project, $contact, 'E2E Faden', 'Passt der Termin so?');

        app(PostMessage::class)($thread, User::query()->firstOrFail(), 'Ja, der Termin steht.');
    }
}
