<?php

namespace Database\Seeders;

use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
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

        foreach (self::CAST as [$name, $partner, $type, $date, $stage, $daysAgo, $source, $venue, $city]) {
            $projectType = ProjectType::from($type);

            $project = Project::create([
                'name' => trim($name.($partner ? ' & '.$partner : '')).' — '.$projectType->label(),
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
        }
    }

    private static function emailFor(string $name): string
    {
        return Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z]+/', '.')->trim('.')
            ->append('@example.test')
            ->value();
    }
}
