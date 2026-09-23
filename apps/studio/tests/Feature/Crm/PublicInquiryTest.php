<?php

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Models\AuditLog;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The endpoint lives on the api surface, which the test process does not
    // boot as — see useSurface() in tests/Pest.php.
    useSurface(Surface::API);

    // The limiter is a cache-backed counter and this endpoint's whole
    // behaviour turns on it, so each test starts from an empty one.
    RateLimiter::clear('inquiry:email:'.sha1('lena@example.test'));
    RateLimiter::clear('inquiry:email:'.sha1('neu@example.test'));
    RateLimiter::clear('inquiry:ip:'.sha1('127.0.0.1'));
});

function submit(array $overrides = []): TestResponse
{
    return test()->postJson('/api/anfragen', array_merge([
        'contact_name' => 'Lena Meier',
        'contact_email' => 'lena@example.test',
        'type' => 'wedding',
        'date' => '2027-06-12',
        'inquiry_text' => 'Wir heiraten im Juni.',
        'consent' => true,
    ], $overrides));
}

it('puts a public submission on the lead board as a new lead with no captor', function () {
    submit()->assertStatus(202)->assertExactJson(['status' => 'received']);

    $lead = Lead::sole();

    expect($lead->stage->key)->toBe(LeadStage::New->value)
        ->and($lead->captured_via)->toBe(CaptureChannel::WebForm)
        ->and($lead->captured_by_id)->toBeNull()
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and(Contact::where('email', 'lena@example.test')->exists())->toBeTrue();
});

it('never says whether the contact already existed', function () {
    Contact::factory()->create(['email' => 'lena@example.test', 'name' => 'Lena Meier']);

    $first = submit(['contact_email' => 'neu@example.test']);
    $second = submit();

    expect($first->status())->toBe($second->status())
        ->and($first->json())->toBe($second->json());
});

it('attaches a returning address to the contact already on file, keeping the stored name', function () {
    $existing = Contact::factory()->create(['email' => 'lena@example.test', 'name' => 'Lena Meier']);

    submit(['contact_name' => 'L. Meier'])->assertStatus(202);

    expect(Contact::where('email', 'lena@example.test')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Lena Meier')
        ->and(Lead::sole()->case->contacts()->sole()->id)->toBe($existing->id);
});

it('returns no internal id and nothing to correlate', function () {
    $response = submit();

    expect(array_keys($response->json()))->toBe(['status'])
        ->and($response->json('status'))->toBe('received');
});

it('refuses the same address twice inside the window, identically to accepting it', function () {
    $accepted = submit();
    $refused = submit(['inquiry_text' => 'Nochmal.']);

    expect($refused->status())->toBe($accepted->status())
        ->and($refused->json())->toBe($accepted->json())
        // The second one is dropped, not captured.
        ->and(Lead::count())->toBe(1);
});

it('drops a submission that filled the hidden field', function () {
    submit(['website' => 'https://spam.example'])->assertStatus(202);

    expect(Lead::count())->toBe(0);
});

it('drops a submission completed faster than a human reads it', function () {
    submit(['elapsed' => 1])->assertStatus(202);

    expect(Lead::count())->toBe(0);
});

it('accepts a submission from a client with no JavaScript, which sends no elapsed', function () {
    submit(['elapsed' => null])->assertStatus(202);

    expect(Lead::count())->toBe(1);
});

it('refuses a submission without consent, the one thing it will report', function () {
    submit(['consent' => false])->assertStatus(422)->assertJsonValidationErrors(['consent']);

    expect(Lead::count())->toBe(0);
});

it('records the consent against the lead, naming what was agreed to', function () {
    submit();

    $row = AuditLog::where('event', 'lead.consent_recorded')->sole();

    expect($row->subject)->toBe(Lead::sole()->public_id)
        ->and($row->context)->toHaveKey('datenschutz_url')
        ->and($row->context['channel'])->toBe('web_form');
});

it('never lets a staff member pick the web-form channel by hand', function () {
    expect(CaptureChannel::staffSelectable())->not->toContain(CaptureChannel::WebForm)
        ->and(CaptureChannel::staffSelectable())->toContain(CaptureChannel::Phone);
});

it('takes a date without answering whether it is free', function () {
    $response = submit(['date' => '2027-06-12']);

    // Nothing in the body speaks about availability, conflicts or other
    // bookings — the studio's calendar stays the studio's.
    expect($response->json())->toBe(['status' => 'received'])
        ->and(Lead::sole()->case->shoots()->sole()->date->format('Y-m-d'))->toBe('2027-06-12');
});
