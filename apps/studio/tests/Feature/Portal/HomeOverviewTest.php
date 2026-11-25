<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormInstance;
use App\Crm\Models\Shoot;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\StartThread;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\StudioSettings;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    // P-01 puts money and messages on the home, so both of T-08's switches
    // are on — the switches' own effect is covered below.
    StudioSettings::query()->update([
        'portal_payment_status_enabled' => true,
        'portal_messaging_mode' => 'any',
    ]);
});

function bookingFor(Contact $contact, array $attributes = []): ClientCase
{
    $project = ClientCase::factory()->create([...$attributes, 'portal_visible' => true]);
    $project->attachContact($contact, CaseContactRole::Primary);

    return $project;
}

function dueInstallment(ClientCase $project, int $amount, string $dueOn): PaymentInstallment
{
    markBooked($project);
    $plan = PaymentPlan::factory()->for($project, 'case')->create();

    return PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => $amount,
        'due_on' => $dueOn,
        'status' => PaymentInstallmentStatus::Scheduled,
    ]);
}

it('gathers what is open across every booking, each naming where it came from', function () {
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => true, 'bank_iban' => 'DE89370400440532013000']);
    Cache::store('redis')->flush();
    $contact = Contact::factory()->create();
    $wedding = bookingFor($contact, ['name' => 'Hochzeitstag']);
    $portrait = bookingFor($contact, ['name' => 'Babybauchshooting']);

    dueInstallment($wedding, 150000, '2026-07-27');
    Quote::factory()->for($portrait, 'case')->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now()->subDay(),
        'total' => 89000,
        'valid_until' => now()->addWeek(),
    ]);

    $pending = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props']['pending'];

    expect(collect($pending)->pluck('kind')->all())->toEqualCanonicalizing(['installment', 'quote'])
        ->and(collect($pending)->pluck('booking')->all())
        ->toEqualCanonicalizing(['Hochzeitstag', 'Babybauchshooting']);
});

it('says nothing is open when nothing is', function () {
    $contact = Contact::factory()->create();
    $project = bookingFor($contact);
    bookingFor($contact);

    dueInstallment($project, 150000, '2026-07-27')->update(['status' => PaymentInstallmentStatus::Paid]);

    $props = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['pending'])->toBe([]);
});

// The switch decides whether a client can open the screen at all; putting a
// row on the home that leads to a 404 would be worse than leaving it out.
it('hides money from the home while the payment-status switch is off', function () {
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_payment_status_enabled' => false]);

    $contact = Contact::factory()->create();
    dueInstallment(bookingFor($contact), 150000, '2026-07-27');
    bookingFor($contact);

    $props = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect(collect($props['pending'])->pluck('kind')->all())->not->toContain('installment');
});

it('counts a thread the studio answered last as waiting on the client', function () {
    $contact = Contact::factory()->create();
    $project = bookingFor($contact);
    bookingFor($contact);

    $thread = app(StartThread::class)($project, $contact, 'Ablauf', 'Frage.');
    app(PostMessage::class)($thread, User::factory()->create(), 'Antwort.');

    $props = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect(collect($props['pending'])->pluck('kind')->all())->toContain('message');
});

it('does not count a thread the client spoke on last', function () {
    $contact = Contact::factory()->create();
    app(StartThread::class)(bookingFor($contact), $contact, 'Ablauf', 'Frage.');
    bookingFor($contact);

    $props = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect(collect($props['pending'])->pluck('kind')->all())->not->toContain('message');
});

it('derives a phase per booking and counts them for the filter', function () {
    $contact = Contact::factory()->create();

    $upcoming = bookingFor($contact);
    Shoot::factory()->for($upcoming, 'case')->on(now()->addMonth()->format('Y-m-d'))->create(['is_primary' => true]);

    $delivered = bookingFor($contact);
    Shoot::factory()->for($delivered, 'case')->on(now()->subYear()->format('Y-m-d'))->create(['is_primary' => true]);
    Gallery::factory()->for($delivered, 'case')->create()->publish();

    bookingFor($contact);

    $props = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['phaseCounts'])->toEqual(['upcoming' => 1, 'delivered' => 1, 'open' => 1]);
});

// The gallery entry point that did not exist at all before P-01.
it('offers every live gallery directly, with its cover and count', function () {
    $contact = Contact::factory()->create();
    $project = bookingFor($contact);
    bookingFor($contact);

    $gallery = Gallery::factory()->for($project, 'case')->create(['title' => 'Verlobungsshooting']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg']);
    $gallery->fresh()->publish();

    $galleries = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props']['galleries'];

    expect($galleries)->toHaveCount(1)
        ->and($galleries[0]['title'])->toBe('Verlobungsshooting')
        ->and($galleries[0]['count'])->toBe(2)
        ->and($galleries[0]['cover'])->toContain('/galerie-bilder/');
});

it('leaves out a gallery whose link has expired, and one nobody published', function () {
    $contact = Contact::factory()->create();
    $project = bookingFor($contact);
    bookingFor($contact);

    Gallery::factory()->for($project, 'case')->create();

    $expired = Gallery::factory()->for($project, 'case')->create();
    $expired->publish();
    $expired->update(['expires_at' => now()->subDay()]);

    $props = test()->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['galleries'])->toBe([]);
});

it('reads every questionnaire for the overview in one query, and the plan of a project once', function () {
    $contact = Contact::factory()->create();

    foreach (range(1, 3) as $n) {
        $project = bookingFor($contact, ['name' => "Hochzeit {$n}"]);
        FormInstance::factory()->for($project, 'case')->create(['shared_at' => now()]);
        dueInstallment($project, 50000, now()->addWeek()->toDateString());
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->actingAs($contact, 'portal')->get('/')->assertOk();

    $count = fn (string $table) => count(array_filter($queries, fn (string $sql) => str_contains($sql, "from \"{$table}\"")));

    expect($count('form_instances'))->toBe(1)
        ->and($count('invoices'))->toBe(3);
});
