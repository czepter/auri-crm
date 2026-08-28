<?php

use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Models\User;
use App\Settings\Models\StudioClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function payload(array $overrides = []): array
{
    return array_merge([
        'contact_name' => 'Annika Steffens',
        'contact_email' => 'a.steffens@posteo.de',
        'contact_phone' => '0171 3948820',
        'people' => [['name' => 'Ruben Falk', 'email' => null, 'phone' => null]],
        'project_name' => 'Steffens & Falk — Hochzeit',
        'type' => ProjectType::Wedding->value,
        'source' => LeadSource::Referral->value,
        'captured_via' => CaptureChannel::Phone->value,
        'date' => '2026-06-14',
        'inquiry_text' => 'Wir heiraten am 14. Juni 2026 auf Gut Basthorst.',
    ], $overrides);
}

it('creates the whole graph from one post', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload())
        ->assertRedirect('/anfragen');

    $project = Project::sole();
    $lead = Lead::sole();
    $shoot = Shoot::sole();

    expect($project->name)->toBe('Steffens & Falk — Hochzeit')
        ->and($project->type->key)->toBe(ProjectType::Wedding->value)
        ->and($project->portal_visible)->toBeFalse()
        ->and($lead->stage->key)->toBe(LeadStage::New->value)
        ->and($lead->captured_by_id)->toBe($this->staff->id)
        ->and($lead->project_id)->toBe($project->id)
        ->and($shoot->is_primary)->toBeTrue()
        ->and($shoot->status->key)->toBe(ShootStatus::Requested->value)
        ->and($shoot->date->format('Y-m-d'))->toBe('2026-06-14');
});

it('creates exactly one shoot, the primary one', function () {
    // Mockup 03 has no repeater: further shoots are added on the project
    // screen, which is what makes is_primary unambiguous at intake.
    $this->actingAs($this->staff, 'staff')->post('/anfragen', payload());

    expect(Shoot::count())->toBe(1);
});

it('creates the partner as a second contact with the partner role', function () {
    $this->actingAs($this->staff, 'staff')->post('/anfragen', payload());

    $project = Project::sole();

    expect(Contact::count())->toBe(2)
        ->and($project->primaryContact()->name)->toBe('Annika Steffens')
        ->and($project->contacts()->wherePivot('role', ProjectContactRole::Partner->value)->sole()->name)
        ->toBe('Ruben Falk');
});

it('reuses an existing contact rather than creating a second one', function () {
    $existing = Contact::factory()->create([
        'name' => 'Annika Steffens',
        'email' => 'a.steffens@posteo.de',
    ]);

    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_id' => $existing->public_id]));

    expect(Contact::where('name', 'Annika Steffens')->count())->toBe(1)
        ->and(Project::sole()->primaryContact()->id)->toBe($existing->id);
});

it('shows a new project in the portal when the contact says so', function () {
    $existing = Contact::factory()->create([
        'email' => 'a.steffens@posteo.de',
        'portal_overrides' => ['portal_visible' => true],
    ]);

    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_id' => $existing->public_id]));

    expect(Project::sole()->portal_visible)->toBeTrue();
});

it('saves without a partner', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['people' => []]))
        ->assertRedirect('/anfragen');

    expect(Contact::count())->toBe(1);
});

it('saves without a date', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['date' => null]))
        ->assertRedirect('/anfragen');

    expect(Shoot::sole()->date)->toBeNull();
});

it('saves without an email, because a contact without one is honest', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_email' => null]))
        ->assertRedirect('/anfragen');

    // Contact::sole() would not do here — the partner is a second contact.
    expect(Project::sole()->primaryContact()->email)->toBeNull();
});

it('warns about a conflicting date but saves anyway', function () {
    $other = Project::factory()->wedding()->create(['name' => 'Meier & Kowalski']);
    Lead::factory()->for($other)->create();
    Shoot::factory()->for($other)->primary()->on('2026-06-14')->create();

    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload())
        ->assertRedirect('/anfragen')
        ->assertSessionHasNoErrors();

    expect(Project::count())->toBe(2)
        ->and(Shoot::where('date', '2026-06-14')->count())->toBe(2);
});

it('warns about a date inside a studio closure but saves anyway', function () {
    // T-09: it warns, never blocks — a closure conflict is informational
    // only, exactly like an existing-shoot conflict already is.
    StudioClosure::factory()->spanning('2026-06-01', '2026-06-30')->create(['label' => 'Weihnachtspause']);

    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['date' => '2026-06-14']))
        ->assertRedirect('/anfragen')
        ->assertSessionHasNoErrors();

    expect(Project::count())->toBe(1)
        ->and(Shoot::where('date', '2026-06-14')->count())->toBe(1);
});

it('writes an audit row for the capture', function () {
    $this->actingAs($this->staff, 'staff')->post('/anfragen', payload());

    $row = AuditLog::where('event', 'lead.captured')->sole();

    expect($row->subject)->toBe(Lead::sole()->public_id)
        ->and($row->context['source'])->toBe('referral');
});

it('creates nothing when validation fails', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['type' => 'not-a-type']))
        ->assertSessionHasErrors('type');

    expect(Project::count())->toBe(0)
        ->and(Contact::count())->toBe(0);
});

it('rolls the whole graph back if any part fails mid-transaction', function () {
    // LeadCaptured fires inside the transaction, after every row is written —
    // the only cheap place to prove the rollback is real rather than proving
    // that validation runs first.
    Event::listen(LeadCaptured::class, fn () => throw new RuntimeException('boom'));

    expect(fn () => app(CaptureInquiry::class)(
        CaptureInquiryData::from(payload()),
        $this->staff,
    ))->toThrow(RuntimeException::class);

    expect(Project::count())->toBe(0)
        ->and(Contact::count())->toBe(0)
        ->and(Lead::count())->toBe(0)
        ->and(Shoot::count())->toBe(0);
});

it('requires a contact name when no existing contact was chosen', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_name' => null, 'contact_id' => null]))
        ->assertSessionHasErrors('contact_name');
});

it('renders the capture page with its options and no integer ids', function () {
    $props = $this->actingAs($this->staff, 'staff')
        ->get('/anfragen/neu')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['types'])->toHaveCount(3)
        ->and($props['sources'])->toHaveCount(5)
        ->and($props['channels'])->toHaveCount(4)
        ->and(integerIdPaths($props))->toBe([]);
});

it('rejects an unauthenticated capture', function () {
    $this->post('/anfragen', payload())->assertRedirect('/login');

    expect(Project::count())->toBe(0);
});

it('records who referred a client when the source is a referral', function () {
    $referrer = Contact::factory()->create(['name' => 'Familie Osterhoff']);

    $project = app(CaptureInquiry::class)(new CaptureInquiryData(
        contact_id: null,
        contact_name: 'Steffens & Falk',
        contact_email: 'steffens@example.test',
        contact_phone: null,
        people: [],
        project_name: 'Steffens & Falk',
        type: ProjectType::Wedding->value,
        source: LeadSource::Referral,
        captured_via: CaptureChannel::Phone,
        date: null,
        inquiry_text: null,
        referred_by_contact_id: $referrer->public_id,
    ), $this->staff);

    expect($project->lead->referredBy->is($referrer))->toBeTrue();
});

it('leaves the referrer null when nobody recorded one', function () {
    // A referral whose referrer nobody wrote down is still a referral. A
    // required field would push whoever takes the call into inventing one.
    $project = app(CaptureInquiry::class)(new CaptureInquiryData(
        contact_id: null,
        contact_name: 'Ohne Vermittler',
        contact_email: 'ohne@example.test',
        contact_phone: null,
        people: [],
        project_name: 'Ohne Vermittler',
        type: ProjectType::Wedding->value,
        source: LeadSource::Referral,
        captured_via: CaptureChannel::Phone,
        date: null,
        inquiry_text: null,
        referred_by_contact_id: null,
    ), $this->staff);

    expect($project->lead->referredBy)->toBeNull();
});

// C-09 (#244): a partner used to arrive as a bare name and could never be
// reached. Every further person is a ProjectContact of their own, with their
// own mail and phone — which is also what gives them their own portal login.
it('captures every person on the inquiry with their own reach', function () {
    test()->actingAs(test()->staff, 'staff')
        ->post('/anfragen', payload([
            'people' => [
                ['name' => 'Tim Erdmann', 'email' => 't.erdmann@example.test', 'phone' => '0170 111'],
                ['name' => 'Mira Sundermann', 'email' => null, 'phone' => null],
            ],
        ]))
        ->assertRedirect();

    $partners = Project::first()->contacts()
        ->wherePivot('role', ProjectContactRole::Partner->value)
        ->get();

    expect($partners->pluck('name')->all())->toBe(['Tim Erdmann', 'Mira Sundermann'])
        ->and($partners->firstWhere('name', 'Tim Erdmann')->email)->toBe('t.erdmann@example.test')
        ->and($partners->firstWhere('name', 'Tim Erdmann')->phone)->toBe('0170 111');
});

it('skips a person row left blank', function () {
    test()->actingAs(test()->staff, 'staff')
        ->post('/anfragen', payload(['people' => []]))
        ->assertRedirect();

    expect(Project::first()->contacts)->toHaveCount(1);
});

// "Sonstige" alone is not a fact: the detail beside it is what the funnel
// splits on, and it must not survive a switch back to a named case.
it('keeps the detail behind Sonstige and drops it when the case changes', function () {
    $this->actingAs($this->staff, 'staff')->post('/anfragen', payload([
        'type' => ProjectType::Other->value,
        'type_other' => 'Tierfotografie',
        'source' => LeadSource::Other->value,
        'source_other' => 'Zeitungsartikel',
        'captured_via' => CaptureChannel::Other->value,
        'captured_via_other' => 'Brief',
    ]))->assertRedirect('/anfragen');

    $project = Project::sole();
    $lead = Lead::sole();

    expect($project->type_other)->toBe('Tierfotografie')
        ->and($lead->source_other)->toBe('Zeitungsartikel')
        ->and($lead->captured_via_other)->toBe('Brief')
        ->and($project->typeLabel())->toBe('Sonstige — Tierfotografie');

    $project->update(['type' => ProjectType::Wedding]);
    $lead->update(['source' => LeadSource::Website]);

    expect($project->fresh()->type_other)->toBeNull()
        ->and($lead->fresh()->source_other)->toBeNull()
        ->and($lead->fresh()->captured_via_other)->toBe('Brief');
});
