<?php

namespace Database\Seeders;

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Contact;
use App\Crm\Models\FormField;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\StartThread;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Models\StudioSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Accounts used only by the Playwright suite. Never registered in
 * DatabaseSeeder — it is invoked explicitly by the e2e setup.
 */
class E2eSeeder extends Seeder
{
    private const IMAGE_WIDTH = 1200;

    private const IMAGE_HEIGHT = 800;

    /** The invitation link's token, spelled the same way in visual.spec.ts. */
    public const INVITATION_TOKEN = 'e2e-einladung';

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
            // The closure is the one switch a spec turns on and off again. If
            // a run dies between the two, this seeder is the way back.
            'portal_maintenance_enabled' => false,
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

        $this->seedInvitation();
        $this->seedOccupiedDate();
        $this->seedEditableRecords();
        $this->seedBooking();
    }

    /**
     * A live invitation with a token the spec can spell. The screen behind it
     * is a 404 for anything else, and the real token only ever exists in an
     * inbox.
     */
    private function seedInvitation(): void
    {
        StaffInvitation::updateOrCreate(
            ['email' => 'e2e-eingeladen@studio.test'],
            [
                'name' => 'E2E Eingeladen',
                'role' => StaffRole::Photographer,
                'token_hash' => StaffInvitation::hash(self::INVITATION_TOKEN),
                'expires_at' => now()->addDays(StaffInvitation::VALID_FOR_DAYS),
                'accepted_at' => null,
                'invited_by_id' => User::query()->firstOrFail()->id,
            ],
        );
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

    }

    /**
     * The booking the portal's document screens hang off: a quote that was
     * sent, a contract that was signed, and a payment plan with something
     * still open on it. None of the three screens renders without its record,
     * and the inquiry above deliberately has none — that is what makes it an
     * inquiry.
     */
    private function seedBooking(): void
    {
        $project = Project::firstOrCreate(
            ['name' => 'E2E Buchung'],
            ['type' => ProjectType::Wedding, 'portal_visible' => true],
        );

        // The quote editor's fixture. It sits on this project rather than on
        // the inquiry above because /projekte lists booked projects only, and
        // that list is how the visual suite reaches the editor.
        $draft = Quote::firstOrCreate(['project_id' => $project->id, 'status' => QuoteStatus::Draft]);

        if ($draft->wasRecentlyCreated) {
            QuoteItem::factory()->for($draft)->create();
        }

        // The gallery and the conversation guard themselves and are seeded
        // whether or not the project is new — they were added after the
        // records below, and a developer's database already holds the rest.
        if (! $project->wasRecentlyCreated) {
            $this->seedGallery($project);
            $this->seedThread($project);

            return;
        }

        $contact = Contact::create(['name' => 'E2E Kundin', 'email' => 'e2e-kundin@example.test']);
        $project->attachContact($contact, ProjectContactRole::Primary);

        Shoot::create([
            'project_id' => $project->id,
            'kind' => ShootKind::Wedding,
            'date' => '2026-09-05',
            'venue_name' => 'Hof Eggers',
            'venue_city' => 'Winsen',
            'is_primary' => true,
            'status' => ShootStatus::Booked,
        ]);

        $quote = Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
            'net' => 440000,
            'total' => 440000,
        ]);
        QuoteItem::factory()->for($quote)->create();

        $contract = Contract::factory()->for($project)->create([
            'status' => ContractStatus::Rendered,
        ]);
        ContractSignature::factory()->for($contract)->create(['contact_id' => $contact->id]);

        $job = Job::factory()->for($project)->create(['booked_by_id' => User::query()->firstOrFail()->id]);

        // In one transaction: the sum-matches-total trigger is deferred to
        // commit, and a plan created outside one is checked while it still
        // has no installments under it.
        DB::transaction(function () use ($job) {
            $plan = PaymentPlan::factory()->for($job)->create(['total' => 200000]);

            PaymentInstallment::factory()->for($plan, 'plan')->count(2)->sequence(
                ['sort_order' => 0, 'due_on' => now()->subWeek()->toDateString()],
                ['sort_order' => 1, 'due_on' => now()->addMonths(2)->toDateString()],
            )->create();
        });

        $this->seedGallery($project);
        $this->seedThread($project);
    }

    /**
     * A delivered gallery with bytes behind it. Rows alone are not enough:
     * GalleryImageController encodes every variant from the original on the
     * s3 disk and answers 404 without one, so a seeded gallery with no
     * objects is six broken tiles.
     */
    private function seedGallery(Project $project): void
    {
        $gallery = Gallery::firstOrCreate([
            'project_id' => $project->id,
            'title' => 'E2E Galerie',
        ]);

        if (! $gallery->wasRecentlyCreated) {
            return;
        }

        $disk = Storage::disk('s3');
        $set = $gallery->sets()->first();

        foreach (range(1, 6) as $index) {
            $key = "galleries/e2e/{$gallery->public_id}/{$index}.jpg";
            $bytes = self::flatJpeg($index);
            $disk->put($key, $bytes);

            Asset::create([
                'gallery_id' => $gallery->id,
                'gallery_set_id' => $set->id,
                'status' => AssetStatus::Ready,
                'key' => $key,
                'filename' => basename($key),
                'byte_size' => strlen($bytes),
                'width' => self::IMAGE_WIDTH,
                'height' => self::IMAGE_HEIGHT,
            ]);
        }

        $gallery->publish();
    }

    /**
     * One flat colour per index. A photograph would be prettier and a pixel
     * baseline would then depend on a JPEG encoder's own version.
     */
    private static function flatJpeg(int $index): string
    {
        $image = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        imagefilledrectangle(
            $image,
            0,
            0,
            self::IMAGE_WIDTH,
            self::IMAGE_HEIGHT,
            imagecolorallocate($image, 30 + $index * 25, 60 + $index * 10, 90),
        );

        ob_start();
        imagejpeg($image, null, 82);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * One conversation with a reply on it. The mailbox renders its empty
     * state without one, and an empty state is not the screen — the thread
     * list, the conversation and both sides of a message only exist here.
     */
    private function seedThread(Project $project): void
    {
        $contact = $project->contacts()->first();

        if ($contact === null || $contact->threads()->where('subject', 'E2E Unterhaltung')->exists()) {
            return;
        }

        $thread = app(StartThread::class)($project, $contact, 'E2E Unterhaltung', 'Passt der Termin so?');

        app(PostMessage::class)($thread, User::query()->firstOrFail(), 'Ja, der Termin steht.');
    }
}
