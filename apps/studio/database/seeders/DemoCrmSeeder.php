<?php

namespace Database\Seeders;

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Models\AutomationRule;
use App\Contracts\Models\Contract;
use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\CrewRole;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\StagePipeline;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\CrmVocabulary;
use App\Engagement\Actions\StartThread;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The cast from mockup 02, so the board can be held next to the screen it was
 * designed from.
 *
 * Three rows are load-bearing rather than decorative: two projects share
 * 2026-06-14 so the derived conflict flag appears on both, one portrait lead
 * has no date so "Termin offen" renders, and one has been silent for eight days
 * so the stale colouring shows.
 *
 * Built through the models rather than with raw inserts, so the seeder keeps
 * proving the schema works.
 */
class DemoCrmSeeder extends Seeder
{
    /** name, partner, type, date, stage, days in stage, source, venue, city */
    private const CAST = [
        ['Sarah Brandt', 'Tim Erdmann', 'wedding', '2026-09-05', 'new', 0, 'referral', 'Hof Eggers', 'Winsen'],
        ['Familie Osterhoff', null, 'portrait', '2026-03-14', 'new', 1, 'website', 'Studio', null],
        ['Nina Kaltenbach', 'Ruben Adler', 'wedding', '2026-06-14', 'new', 1, 'instagram', 'Gut Basthorst', 'Basthorst'],
        ['Charlotte Vehse', 'Paul Rieck', 'wedding', '2026-08-22', 'contacted', 8, 'website', 'Schloss Reinbek', 'Reinbek'],
        ['Mira Sundermann', null, 'portrait', null, 'contacted', 4, 'referral', null, null],
        ['Jasmin Petersen', 'Kolja Petersen', 'wedding', '2026-07-11', 'contacted', 2, 'referral', 'Landhaus Scherrer', 'Hamburg'],
        ['Annika Reuter', 'Finn Sölter', 'wedding', '2026-05-30', 'quote_sent', 3, 'wedding_fair', 'Elbspeicher', 'Hamburg'],
        ['Lea Hoffmann', 'Jonas Behr', 'wedding', '2026-10-03', 'quote_sent', 6, 'instagram', 'Gut Wulksfelde', 'Tangstedt'],
        ['Marie Ahrens', 'David Kroll', 'wedding', '2026-07-25', 'quote_accepted', 1, 'referral', 'Kaispeicher', 'Hamburg'],
        ['Sophie Lang', 'Erik Voss', 'wedding', '2026-09-19', 'contract_sent', 2, 'website', 'Gut Basthorst', 'Basthorst'],
        ['Meier & Kowalski', null, 'wedding', '2026-06-14', 'booked', 12, 'referral', 'Gut Basthorst', 'Basthorst'],
    ];

    public function run(): void
    {
        $staff = User::query()->first() ?? User::factory()->create();

        // Before anything else: RequireStudioOnboarding gates the entire
        // admin surface on this, so a demo database that skips it opens on
        // /einrichtung/studio instead of the Übersicht — indistinguishable,
        // from the browser, from a broken install.
        $this->seedStudioOnboarding($staff);

        // Before the cast, so booking the wedding below produces its tasks
        // through the listener rather than having them written in by hand.
        $this->seedTaskTemplates();

        // Keyed by the cast's own contact name, so seedDashboardOccasions()
        // below can reach back into rows the loop already built rather than
        // re-querying them by their assembled "name & partner" project name.
        $projects = [];

        foreach (self::CAST as [$name, $partner, $type, $date, $stage, $daysAgo, $source, $venue, $city]) {
            $projectType = ProjectType::from($type);

            // Just the names, the way CaptureInquiry takes them from whoever
            // types the inquiry in. Appending the type here spelled every
            // project "Meier & Kowalski — Hochzeit", which .who truncates at
            // its 150px to "Meier & Kowalski — …" on every row of the
            // Übersicht — the type restated in a column that has no room for
            // it, next to a block already headed "Nächste Shootings".
            $project = Project::create([
                'name' => trim($name.($partner ? ' & '.$partner : '')),
                'type' => $projectType,
            ]);

            $project->attachContact(
                Contact::create(['name' => $name, 'email' => self::emailFor($name)]),
                ProjectContactRole::Primary,
            );

            if ($partner) {
                $project->attachContact(
                    Contact::create(['name' => $partner]),
                    ProjectContactRole::Partner,
                );
            }

            Shoot::create([
                'project_id' => $project->id,
                'kind' => $projectType === ProjectType::Wedding ? 'Hochzeitstag' : 'Portrait',
                'date' => $date,
                'venue_name' => $venue,
                'venue_city' => $city,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            $lead = Lead::factory()->for($project)->atStage(LeadStage::from($stage), $daysAgo)->create([
                'source' => LeadSource::from($source),
                'captured_by_id' => $staff->id,
            ]);

            // The cast is built from models rather than CaptureInquiry, so the
            // audit row that action would have written has to be written here —
            // without it every seeded project's Aktivität feed is empty. Audit
            // rows are append-only, so the timestamp is travelled to rather than
            // corrected afterwards.
            Carbon::setTestNow($lead->stage_entered_at);

            AuditLogger::record(
                event: 'lead.captured',
                actor: $staff,
                context: ['source' => $source, 'via' => $lead->captured_via->value],
                subject: $lead->public_id,
                projectId: $project->id,
            );

            Carbon::setTestNow();

            $projects[$name] = $project;

            // The one booked project gets mockup 04's full shape, so the
            // project screen has something to be held next to the screen it
            // was designed from.
            if ($stage === 'booked') {
                $this->buildOutTheWedding($project, $staff);
            }
        }

        // After the cast, and after the booked wedding: the occasions below
        // lean on the Job that buildOutTheWedding() only just produced, and
        // on the automation rule set the sweep would otherwise seed on its
        // own schedule.
        $this->seedDashboardOccasions($projects);
    }

    /**
     * Completes the first-run wizard T-01's own migration already gives every
     * install a single, empty studio_settings row for. Every column that row
     * ships with a real default (vat_rate, quote_validity_days, working_days,
     * ...) already reads as a working studio; only the Studio-Profil pane
     * (T-02: display_name, legal_name, address, ...) is nullable with no
     * default, and RequireStudioOnboarding's redirect to /einrichtung/studio
     * fires on onboarding_completed_at alone, not on the profile being filled
     * in — so this writes both, in the one order that matters: a plausible
     * identity first, the completion stamp last.
     *
     * Through StudioSettingsResolver::set(), the same seam
     * StudioSettingsController::update() and StudioOnboardingController::
     * complete() call — never a raw update on the table — so this seeder
     * keeps proving the resolver's own cache-bust and audit-row guarantees
     * rather than writing around them.
     *
     * Guarded the same way DatabaseSeeder guards its own call into this
     * class: db:seed against production is exactly the command someone runs
     * once against the wrong environment, and this method skipping stays
     * true even if DemoCrmSeeder is ever invoked some other way than through
     * DatabaseSeeder.
     */
    private function seedStudioOnboarding(User $actor): void
    {
        if (app()->isProduction()) {
            return;
        }

        $settings = app(StudioSettingsResolver::class);

        foreach ([
            'display_name' => 'Nordlicht Fotografie',
            'legal_name' => 'Nordlicht Fotografie GmbH',
            'legal_form' => 'GmbH',
            'street' => 'Elbchaussee 12',
            'postcode' => '22765',
            'city' => 'Hamburg',
            'country' => 'Deutschland',
            'vat_id' => 'DE123456789',
            'register_number' => 'HRB 123456',
            'register_court' => 'Amtsgericht Hamburg',
            'representative' => 'Christian Bergmann',
            'website' => 'https://nordlicht-fotografie.test',
        ] as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        $settings->set('onboarding_completed_at', now(), $actor);
    }

    /**
     * The studio's standing list. Three is enough to show the two things that
     * matter: an offset before the day, one after it, and a template that only
     * applies to weddings.
     */
    private function seedTaskTemplates(): void
    {
        foreach ([
            ['Ablauf bestätigen', TaskAudience::Client, -14, null],
            ['Familienliste einholen', TaskAudience::Client, -7, 'Hochzeitstag'],
            ['Crew-Briefing verschicken', TaskAudience::Internal, -1, null],
        ] as [$title, $audience, $offset, $kind]) {
            TaskTemplate::updateOrCreate(
                ['title' => $title],
                ['audience' => $audience, 'day_offset' => $offset, 'shoot_kind' => $kind],
            );
        }
    }

    /**
     * Mockup 04's wedding, in full: four occasions in three different pipeline
     * positions, a crew that differs per shoot, and the notes the studio wrote
     * to itself.
     *
     * Walked through BookProject and AdvanceShootStatus rather than written
     * with statuses set by hand — same reasoning as the rest of this seeder.
     * The demo data is produced by the code paths the studio uses, so a broken
     * guard shows up here rather than only in a test.
     */
    private function buildOutTheWedding(Project $project, User $staff): void
    {
        $christian = User::updateOrCreate(
            ['email' => 'christian@nordlicht.test'],
            ['name' => 'Christian Bergmann', 'password' => 'password', 'role' => StaffRole::Owner],
        );
        $nina = User::updateOrCreate(
            ['email' => 'nina@nordlicht.test'],
            ['name' => 'Nina Sturm', 'password' => 'password', 'role' => StaffRole::SecondShooter],
        );

        // The primary shoot already exists from the cast; this is the wedding
        // day itself, so it only gains the detail the project screen shows.
        // Anchored to today rather than to the mockup's 2026 dates: the
        // Übersicht leads with the next shooting day, and a demo whose every
        // shoot is in the past shows an empty screen that looks like a bug.
        $wedding = $project->primaryShoot;
        $wedding->update([
            'label' => 'Hochzeitstag',
            'date' => now()->addDays(5)->format('Y-m-d'),
            'call_time' => '09:00',
            'end_time' => '23:00',
        ]);

        $engagement = Shoot::create([
            'project_id' => $project->id,
            'kind' => 'Verlobungsshooting',
            'label' => 'Verlobungsshooting',
            'date' => now()->subDays(140)->format('Y-m-d'),
            'call_time' => '14:00',
            'end_time' => '16:00',
            'venue_name' => 'Jenischpark',
            'venue_city' => 'Hamburg',
            'sort_order' => 1,
        ]);

        $registry = Shoot::create([
            'project_id' => $project->id,
            'kind' => 'Feier',
            'label' => 'Standesamt',
            'date' => now()->subDays(37)->format('Y-m-d'),
            'call_time' => '11:00',
            'end_time' => '12:30',
            'venue_name' => 'Rathaus Altona',
            'venue_city' => 'Hamburg',
            'sort_order' => 2,
        ]);

        // No venue and no times: "Ort und Uhrzeit noch nicht festgelegt" is a
        // real state the screen has to render.
        $afterWedding = Shoot::create([
            'project_id' => $project->id,
            'kind' => 'Portrait',
            'label' => 'After-Wedding-Shooting',
            'date' => now()->addDays(26)->format('Y-m-d'),
            'sort_order' => 3,
        ]);

        // Booking moves all four to booked in one transaction.
        app(BookProject::class)($project, $christian);

        // Then the two that have happened walk forward, and the wedding day
        // sits in prep — so the derived header reads the least-advanced of
        // them rather than the furthest.
        $advance = app(AdvanceShootStatus::class);
        $stage = fn (ShootStatus $status) => app(CrmVocabulary::class)
            ->byKey(StagePipeline::Project, $status->value);

        foreach ([$engagement, $registry] as $done) {
            $advance($done->refresh(), $stage(ShootStatus::Delivered), $christian);
        }

        $advance($wedding->refresh(), $stage(ShootStatus::Prep), $christian);

        CrewAssignment::create([
            'shoot_id' => $wedding->id,
            'user_id' => $christian->id,
            'role' => CrewRole::LeadPhotographer,
        ]);
        CrewAssignment::create([
            'shoot_id' => $wedding->id,
            'user_id' => $nina->id,
            'role' => CrewRole::SecondShooter,
        ]);
        CrewAssignment::create([
            'shoot_id' => $engagement->id,
            'user_id' => $christian->id,
            'role' => CrewRole::LeadPhotographer,
        ]);
        CrewAssignment::create([
            'shoot_id' => $afterWedding->id,
            'user_id' => $nina->id,
            'role' => CrewRole::Assistant,
        ]);

        foreach ([
            [$christian, 'Großmutter (Lenas Seite) reist gegen 18 Uhr ab — Gruppenfotos deshalb direkt an den Anfang des Empfangs gezogen. Im Ablauf schon berücksichtigt, Wunschliste Gruppe 1 zuerst.'],
            [$nina, 'Location-Check Gut Basthorst: Festscheune hat starkes Gegenlicht ab ca. 20 Uhr durch das Westfenster. Für den Eröffnungstanz Position auf der Ostseite, sonst Silhouetten.'],
            [$christian, 'Über Empfehlung von Familie Osterhoff gekommen — bei Lieferung eine Karte an die Osterhoffs denken. Paar wünscht ausdrücklich Reportage, wenig Gestelltes.'],
        ] as [$author, $body]) {
            InternalNote::create([
                'project_id' => $project->id,
                'author_id' => $author->id,
                'body' => $body,
            ]);
        }
    }

    /**
     * Mockup 01's four columns and its header link, each fed by the exact
     * predicate its reader checks — App\Quotes\Actions\ReadInquiryColumn,
     * App\Payments\Actions\ReadPaymentColumn, App\Gallery\Actions\
     * ReadAfterDeliveryColumn and DashboardController::unanswered() — rather
     * than by a row shaped to merely look right next to the mockup.
     *
     * Every project used here is one the cast above already built; nothing
     * new is invented except the occasion each column is watching for.
     */
    private function seedDashboardOccasions(array $projects): void
    {
        // config/automation.php ships the rule set; only the console command
        // loads it into the database, and a Seeder cannot $this->call() one.
        Artisan::call('automation:seed-default-rules');

        $this->seedChasedQuote($projects['Annika Reuter']);
        $this->seedAcceptedQuoteWithoutContract($projects['Marie Ahrens']);
        $this->seedUnsignedContract($projects['Sophie Lang']);
        $this->seedOverdueInstallment($projects['Meier & Kowalski']);
        $this->seedReferralAndExpiringGallery($projects['Meier & Kowalski'], $projects['Familie Osterhoff']);
        $this->seedUnansweredThread($projects['Annika Reuter']);
    }

    /**
     * A sent quote running out with the reminders spent: valid_until sits
     * inside ReadInquiryColumn::EXPIRY_WINDOW_DAYS, and the two AutomationRun
     * rows are real, fired through FireAutomationRule against two different
     * trigger dates — the "2× erinnert" the row renders is a count of these,
     * not a string written to look like one.
     */
    private function seedChasedQuote(Project $project): void
    {
        $project->primaryContact()?->update(['phone' => '+49 40 5550101']);

        Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Sent,
            'sent_at' => now()->subDays(9),
            'valid_until' => now()->addDays(5),
            'total' => 350000,
        ]);

        $rule = AutomationRule::query()->where('key', 'quote-follow-up')->firstOrFail();
        $fire = app(FireAutomationRule::class);

        // Two distinct trigger dates so the idempotency key differs and both
        // claims land — the same rule pinging the same project on two
        // separate weeks, which is exactly what "2×" means on the row.
        $fire($rule, $project, now()->subDays(9)->toDateString());
        $fire($rule, $project, now()->subDays(2)->toDateString());
    }

    /** Accepted, and nothing has rendered a contract for it yet. */
    private function seedAcceptedQuoteWithoutContract(Project $project): void
    {
        Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Accepted,
            'sent_at' => now()->subDays(10),
            'accepted_at' => now()->subDays(3),
            'total' => 420000,
        ]);
    }

    /** Rendered, aged past ReadInquiryColumn::UNSIGNED_AFTER_DAYS, still no signature. */
    private function seedUnsignedContract(Project $project): void
    {
        $project->primaryContact()?->update(['phone' => '+49 40 5550102']);

        $contract = Contract::factory()->for($project)->rendered()->create();

        // created_at drives the row's "N Tage unsigniert" — timestamps only
        // accept an override outside Eloquent's own auto-touch, which a plain
        // create() array does not bypass.
        $contract->timestamps = false;
        $contract->forceFill([
            'created_at' => now()->subDays(6),
            'rendered_at' => now()->subDays(6),
        ])->save();
    }

    /**
     * The plan and its one overdue installment share a transaction: the
     * sum-matches-total trigger is deferred to commit, but each Eloquent
     * create() is its own transaction unless one is opened explicitly, and a
     * plan committed alone with no installments yet would trip it.
     *
     * DetectOverdueInstallments always stamps overdue_notified_at with
     * `now()`, so running it here would read "seit 0 Tagen" — the opposite of
     * what this row demonstrates. It runs anyway, for the real guard and the
     * InstallmentOverdue dispatch, and the timestamp is then moved back by
     * hand to the nine days ReadPaymentColumn::DUNNING_EXHAUSTED_DAYS needs.
     */
    private function seedOverdueInstallment(Project $project): void
    {
        $project->primaryContact()?->update(['phone' => '+49 40 5550103']);

        $installment = DB::transaction(function () use ($project) {
            $plan = PaymentPlan::factory()->for($project->job)->create(['total' => 150000]);

            return PaymentInstallment::factory()->for($plan, 'plan')->create([
                'amount' => 150000,
                'due_on' => now()->subDays(20)->toDateString(),
            ]);
        });

        app(DetectOverdueInstallments::class)->markOverdue($installment);
        $installment->update(['overdue_notified_at' => now()->subDays(9)]);
    }

    /**
     * Both of Nach der Lieferung's rows, on the one project already booked
     * with a Job: a referral naming the contact InternalNote already credits
     * above, and a gallery whose expiry sits inside
     * ReadAfterDeliveryColumn::EXPIRY_WINDOW_DAYS.
     *
     * expires_at is set directly rather than through Gallery::publish():
     * publish() only stamps it when gallery_default_expiry_days is set, and
     * that setting defaults to null — every published gallery keeps no expiry
     * until a studio asks for one. This row needs an expiry to exist
     * regardless of that setting, so it is written the same way publish()
     * itself would have, once retention is configured.
     */
    private function seedReferralAndExpiringGallery(Project $project, Project $referrerProject): void
    {
        $project->lead->update([
            'referred_by_contact_id' => $referrerProject->primaryContact()?->id,
        ]);
        $referrerProject->primaryContact()?->update(['phone' => '+49 40 5550104']);

        Gallery::factory()->for($project)->create([
            'title' => 'Meier & Kowalski — Hochzeit',
            'expires_at' => now()->addDays(12),
        ]);
    }

    /**
     * Client-initiated, the only way E-06 ever starts a thread — see
     * ReadAfterDeliveryColumn's own docblock for why a machine-sent
     * thank-you would be the wrong shape here, and by the same reasoning
     * this screen owes a real reply, not an invented one.
     */
    private function seedUnansweredThread(Project $project): void
    {
        app(StartThread::class)(
            $project,
            $project->primaryContact(),
            'Frage zum Angebot',
            'Hallo, wir hätten noch eine kurze Frage zum Angebot — meldet ihr euch?',
        );
    }

    private static function emailFor(string $name): string
    {
        return Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z]+/', '.')->trim('.')
            ->append('@example.test')
            ->value();
    }
}
