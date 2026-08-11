<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\TimelineEntry;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Actions\ReviseQuote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

// renderedContract() lives in tests/Pest.php — see the note there.

uses(RefreshDatabase::class);

// integerIdPaths() lives in tests/Pest.php — see the note there.
//
// N-04: this Pest process cannot call into packages/mcp's Node process to
// check an MCP tool's own CallToolResult the way it checks an Inertia prop
// or a JSON body above — there is no PHP call that reaches it. The same
// invariant is enforced there instead, by a JS port of integerIdPaths() —
// see packages/mcp/src/tools/write.test.ts's "never puts an integer
// identifier into a write tool's result" suite, which proves it against
// capture_lead, advance_lead_stage and add_internal_note's real result
// shapes (the same LeadController/InternalNoteController responses this
// file's own Integration tests already cover).

it('never puts an integer identifier into an authenticated inertia page prop', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the login page', function () {
    // A staff account has to exist for /login to render at all — an empty
    // table sends every visitor to /einrichtung instead. See SetupTest.
    User::factory()->create();

    $props = $this->get('/login')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('exposes the signed-in user as a public id', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['auth']['user']['id'])->toBe($user->public_id)
        ->and($props['auth']['user'])->not->toHaveKey('mfa_secret');
});

it('never puts an integer identifier into any crm page', function (string $path) {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get($path)->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
})->with(['/anfragen', '/anfragen/neu', '/kontakte']);

it('never puts an integer identifier into a project page', function (string $suffix) {
    // Populated on purpose: an empty project proves nothing, since the keys
    // that escape belong to the shoots, the crew, the timeline (E-04), the
    // notes, the job and — M-03 — the payment plan and its installments.
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->primary()->create();
    CrewAssignment::factory()->for($shoot)->create();
    TimelineEntry::factory()->for($shoot)->create();
    InternalNote::factory()->for($project)->create();
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 200]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 0]);

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}{$suffix}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
})->with(['', '/shootings']);

it('never puts an integer identifier into the package catalog', function () {
    // Populated on purpose, per the note above the project test: an empty
    // package proves nothing, since the key that escapes belongs to an item.
    $user = User::factory()->withMfa()->create();
    $package = Package::factory()->create();
    PackageItem::factory()->for($package)->create();

    $listProps = $this->actingAs($user, 'staff')->get('/pakete')->assertOk()->viewData('page')['props'];
    expect(integerIdPaths($listProps))->toBe([]);

    $editProps = $this->get("/pakete/{$package->public_id}")->assertOk()->viewData('page')['props'];
    expect(integerIdPaths($editProps))->toBe([]);
});

it('never puts an integer identifier into a project\'s quotes page', function () {
    // Populated on purpose, per the note above the project test: an empty
    // quote proves nothing, since the key that escapes belongs to an item.
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    $quote = Quote::factory()->for($project)->create();
    QuoteItem::factory()->for($quote)->create();

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}/angebote")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into a project\'s quotes page once a quote has been sent and revised', function () {
    // Q-06: predecessorId is the one new cross-model reference this story's
    // QuoteData adds — a quote referencing another quote — and it has to
    // cross the same public_id boundary every other FK in this Data layer
    // does. Populated on purpose: predecessor_id stays null on every quote
    // NoInternalIdsTest already exercises, so only a revised pair proves it.
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    $predecessor = Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'number' => 'AN-2026-1']);
    QuoteItem::factory()->for($predecessor)->create();
    app(ReviseQuote::class)($predecessor);

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}/angebote")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the settings screen', function () {
    $user = User::factory()->withMfa()->create(['role' => StaffRole::Owner]);

    $props = $this->actingAs($user, 'staff')->get('/einstellungen')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the document template screens', function () {
    $user = User::factory()->withMfa()->create();
    $template = DocumentTemplate::factory()->create();

    $listProps = $this->actingAs($user, 'staff')->get('/dokumentvorlagen')->assertOk()->viewData('page')['props'];
    expect(integerIdPaths($listProps))->toBe([]);

    $editProps = $this->get("/dokumentvorlagen/{$template->public_id}")->assertOk()->viewData('page')['props'];
    expect(integerIdPaths($editProps))->toBe([]);
});

it('never puts an integer identifier into a project\'s contract tab', function () {
    Storage::fake('local');

    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    renderedContract($project);
    DocumentTemplate::factory()->create();

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('detects an integer id when one is present', function () {
    expect(integerIdPaths(['data' => ['id' => 7, 'name' => 'x']]))->toBe(['$.data.id']);
    expect(integerIdPaths(['data' => ['id' => 'abc', 'name' => 'x']]))->toBe([]);
});

it('never puts an integer identifier into the portal switcher', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $first = Project::factory()->create(['portal_visible' => true]);
    $second = Project::factory()->create(['portal_visible' => true]);
    $first->attachContact($contact, ProjectContactRole::Primary);
    $second->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the portal contract screen once signed, and the sign route takes only a public id', function () {
    useSurface(Surface::PORTAL);
    Storage::fake('local');
    // T-08's "sign contracts" switch defaults to off — this test exercises
    // the sign action itself, so the switch is on for it. Cache::flush()
    // first: StudioSettingsResolver caches the row (T-01), and a row already
    // cached by an earlier test in the same suite run would otherwise still
    // read as off.
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_sign_contracts_enabled' => true]);

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    // The URL itself names the project by public id, never by its integer
    // primary key — this is the request that writes the signature row.
    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertRedirect();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into a portal project screen', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);
    Shoot::factory()->for($project)->primary()->create();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into a project\'s messages tab', function () {
    // Populated on purpose, per the note above the project test: an empty
    // thread proves nothing, since the key that escapes belongs to a message.
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    app(App\Engagement\Actions\StartThread::class)($project, $contact, 'Thema', 'Nachricht.');

    $props = $this->actingAs($user, 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the portal messages screen', function () {
    useSurface(Surface::PORTAL);
    // T-08's "messaging" switch defaults to off — this test exercises the
    // thread screen itself, so the switch is on, the same way the sign and
    // accept tests above turn their own switches on.
    \Illuminate\Support\Facades\Cache::store('redis')->flush();
    \App\Settings\Models\StudioSettings::query()->update(['portal_messaging_enabled' => true]);

    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);
    app(App\Engagement\Actions\StartThread::class)($project, $contact, 'Thema', 'Nachricht.');

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/nachrichten")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the portal quote screen once accepted, and the accept route takes only a public id', function () {
    useSurface(Surface::PORTAL);
    // T-08's "accept quotes online" switch defaults to off — this test
    // exercises the accept action itself, so the switch is on for it.
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => true]);

    $project = Project::factory()->create(['portal_visible' => true]);
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'number' => 'AN-2026-1',
        'sent_at' => now(),
    ]);
    QuoteItem::factory()->for($quote)->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    // The URL itself names the project by public id, never by its integer
    // primary key — this is the request that writes the acceptance.
    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertRedirect();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});
