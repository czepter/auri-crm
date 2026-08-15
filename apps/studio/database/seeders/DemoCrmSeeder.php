<?php

namespace Database\Seeders;

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\CrewRole;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\TaskTemplate;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
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

        // Before the cast, so booking the wedding below produces its tasks
        // through the listener rather than having them written in by hand.
        $this->seedTaskTemplates();

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
                'kind' => $projectType === ProjectType::Wedding ? ShootKind::Wedding : ShootKind::Portrait,
                'date' => $date,
                'venue_name' => $venue,
                'venue_city' => $city,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            Lead::factory()->for($project)->atStage(LeadStage::from($stage), $daysAgo)->create([
                'source' => LeadSource::from($source),
                'captured_by_id' => $staff->id,
            ]);

            // The one booked project gets mockup 04's full shape, so the
            // project screen has something to be held next to the screen it
            // was designed from.
            if ($stage === 'booked') {
                $this->buildOutTheWedding($project, $staff);
            }
        }
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
            ['Familienliste einholen', TaskAudience::Client, -7, ShootKind::Wedding],
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
            'kind' => ShootKind::Engagement,
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
            'kind' => ShootKind::Reception,
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
            'kind' => ShootKind::Portrait,
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

        foreach ([$engagement, $registry] as $done) {
            $advance($done->refresh(), ShootStatus::Delivered, $christian);
        }

        $advance($wedding->refresh(), ShootStatus::Prep, $christian);

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

    private static function emailFor(string $name): string
    {
        return Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z]+/', '.')->trim('.')
            ->append('@example.test')
            ->value();
    }
}
