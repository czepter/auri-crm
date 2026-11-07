<?php

use App\Automation\Data\AutomationRuleData;
use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationCondition;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Events\CaseBooked;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\ProjectType;
use App\Documents\Support\MergeFieldVocabulary;
use App\Enums\StaffRole;
use App\Models\StudioSettings;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lists the rules and edits one', function () {
    $owner = staffMember(StaffRole::Admin);
    $rule = AutomationRule::factory()->create(['name' => 'Shooting-Erinnerung']);

    $this->actingAs($owner, 'staff')
        ->get('/einstellungen/automationen')
        ->assertInertia(fn ($page) => $page->component('Settings/Automations')->has('rules', 1));

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", [
            'name' => 'Crew — Tag vorher',
            'anchor' => 'per_shoot',
            'offset_days' => 1,
            'direction' => 'before',
            'recipient_type' => 'crew',
            'action' => 'task',
            'condition' => 'payment_open',
            'notification_subject' => 'Morgen: {{ project.name }}',
            'notification_body' => 'Ausrüstung packen.',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($rule->fresh()->recipient_type)->toBe(AutomationRecipient::Crew)
        ->and($rule->fresh()->action)->toBe(AutomationAction::Task)
        ->and($rule->fresh()->condition)->toBe(AutomationCondition::PaymentOpen);
});

// Eine Ereignisregel traegt weder Anker noch Abstand — AutomationRule::booted()
// weist eine zurueck, die beides hat.
it('saves an event rule, which carries no anchor', function () {
    $rule = AutomationRule::factory()->event(CaseBooked::class)->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", [
            'name' => 'Buchungsbestätigung',
            'recipient_type' => 'client',
            'condition' => null,
            'notification_subject' => 'Ihre Buchung ist bestätigt',
            'notification_body' => 'Wir freuen uns.',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($rule->fresh()->notification_body)->toBe('Wir freuen uns.')
        ->and($rule->fresh()->anchor)->toBeNull();
});

// AuthorizesStaffSettings antwortet mit 404, nicht 403: eine Flaeche, die
// diese Rolle nicht hat, existiert fuer sie nicht.
it('keeps the screen away from a role that may not manage settings', function () {
    $this->actingAs(staffMember(StaffRole::Manager), 'staff')
        ->get('/einstellungen/automationen')
        ->assertNotFound();
});

it('rejects an unknown merge field and an invoice field off the instalment anchor', function () {
    $rule = AutomationRule::factory()->create();
    $payload = [
        'name' => 'Erinnerung',
        'anchor' => 'per_shoot',
        'offset_days' => 1,
        'direction' => 'before',
        'recipient_type' => 'client',
        'notification_subject' => 'Hallo {{ contact.nmae }}',
        'notification_body' => 'Rechnung {{ invoice.number }}',
        'is_active' => true,
    ];
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", $payload)
        ->assertSessionHasErrors([
            'notification_subject' => 'Unbekanntes Feld: contact.nmae.',
            'notification_body' => 'Für diesen Auslöser nicht verfügbar: Rechnungsnummer.',
        ]);

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", [
            ...$payload,
            'anchor' => 'installment_due',
            'notification_subject' => 'Hallo {{ contact.name }}',
        ])
        ->assertSessionHasNoErrors();
});

it('sends every merge field to the editor, invoice fields flagged', function () {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page
            ->where('mergeFields', fn ($fields) => collect($fields)->contains(fn ($f) => $f['path'] === 'invoice.number'
                && $f['needsPayment'] === true && $f['label'] === 'Rechnungsnummer' && $f['group'] === 'Rechnung')
                && collect($fields)->contains(fn ($f) => $f['path'] === 'contact.name' && $f['needsPayment'] === false))
            ->has('previewProjects'));
});

it('mails the rendered sample to the signed-in user by mail only', function () {
    Notification::fake();
    $rule = AutomationRule::factory()->create();
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->post("/einstellungen/automationen/{$rule->public_id}/testmail", [
            'subject' => 'Shooting: {{ project.name }}',
            'body' => 'Hallo {{ contact.name }}',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', "Testmail an {$owner->email} gesendet.");

    Notification::assertSentTo($owner, AutomationReminder::class, fn ($n, $channels) => $channels === ['mail']
        && $n->subject() === 'Shooting: Hochzeit Meier & Kowalski'
        && $n->body() === 'Hallo Lena Meier');

    $this->actingAs($owner, 'staff')
        ->post("/einstellungen/automationen/{$rule->public_id}/testmail", ['subject' => '', 'body' => ''])
        ->assertSessionHasErrors(['subject', 'body']);
});

it('previews an empty body as json', function () {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?subject=&body=")
        ->assertOk()
        ->assertJsonPath('subject', '')
        ->assertJsonStructure(['subject', 'html']);
});

it('previews against a real project', function () {
    $rule = AutomationRule::factory()->create();
    $project = ClientCase::factory()->create(['name' => 'Taufe Schulz']);
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query([
            'subject' => 'Re: {{ project.name }}',
            'body' => 'Zu {{ project.name }}',
            'project' => $project->public_id,
        ]))
        ->assertOk()
        ->assertJsonPath('subject', 'Re: Taufe Schulz')
        ->assertJsonPath('html', fn ($html) => str_contains($html, 'Zu Taufe Schulz'));

    $this->actingAs($owner, 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page
            ->where('previewProjects.0', ['value' => '', 'label' => 'Beispiel'])
            ->where('previewProjects.1', ['value' => $project->public_id, 'label' => 'Taufe Schulz'])
            ->where('testRecipient', $owner->email));
});

it('offers only projects of the rule type, after the sample entry', function () {
    $rule = AutomationRule::factory()->create(['project_type' => 'wedding']);
    ClientCase::factory()->create(['name' => 'Taufe Schulz', 'type' => 'family']);
    ClientCase::factory()->create(['name' => 'Hochzeit Kern', 'type' => 'wedding']);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page->where('previewProjects', fn ($projects) => collect($projects)->pluck('label')->all() === ['Beispiel', 'Hochzeit Kern']));
});

it('searches all projects of the type when the preview is asked for a term', function () {
    $rule = AutomationRule::factory()->create();
    ClientCase::factory()->count(11)->create(['type' => 'wedding']);
    $old = ClientCase::factory()->create(['name' => 'Taufe Schulz', 'type' => 'wedding', 'created_at' => now()->subYear()]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query(['project_type' => 'wedding', 'q' => 'schulz']))
        ->assertOk()
        ->assertJsonPath('projects', [['value' => '', 'label' => 'Beispiel'], ['value' => $old->public_id, 'label' => 'Taufe Schulz']]);
});

it('keeps the picked project in the list when it is outside the latest ten', function () {
    $rule = AutomationRule::factory()->create();
    ClientCase::factory()->count(11)->create(['type' => 'wedding']);
    $old = ClientCase::factory()->create(['name' => 'Taufe Schulz', 'type' => 'wedding', 'created_at' => now()->subYear()]);
    $other = ClientCase::factory()->create(['type' => 'family', 'created_at' => now()->subYear()]);
    $owner = staffMember(StaffRole::Admin);
    $url = fn (array $query) => "/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query($query);

    $this->actingAs($owner, 'staff')
        ->getJson($url(['project_type' => 'wedding', 'project' => $old->public_id]))
        ->assertJsonPath('projects', fn ($projects) => collect($projects)->pluck('value')->contains($old->public_id)
            && count($projects) === 12);

    $this->actingAs($owner, 'staff')
        ->getJson($url(['project_type' => 'wedding', 'project' => $other->public_id]))
        ->assertJsonPath('projects', fn ($projects) => ! collect($projects)->pluck('value')->contains($other->public_id));
});

it('answers a test mail for a deleted project with errors keyed by field', function () {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->postJson("/einstellungen/automationen/{$rule->public_id}/testmail", [
            'subject' => 'Betreff',
            'body' => 'Text',
            'project' => 'gone',
            'anchor' => 'nope',
            'project_type' => 'nope',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project' => 'Der gewählte Wert für Projekt ist ungültig.', 'anchor', 'project_type']);
});

it('names the fields in German when the test mail is invalid', function () {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/einstellungen/automationen/{$rule->public_id}/testmail", ['subject' => '', 'body' => ''])
        ->assertSessionHasErrors([
            'subject' => 'Das Feld Betreff muss ausgefüllt werden.',
            'body' => 'Das Feld Text muss ausgefüllt werden.',
        ]);
});

it('previews the sample with the real studio data', function () {
    StudioSettings::current()->update(['legal_name' => 'Foto Kern GmbH']);
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query([
            'subject' => '{{ studio.name }} / {{ project.name }}',
        ]))
        ->assertJsonPath('subject', 'Foto Kern GmbH / Hochzeit Meier & Kowalski');
});

it('keeps invoice fields as written in the preview unless the anchor is an instalment', function (?string $anchor, string $expected) {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query(array_filter([
            'subject' => 'Rechnung {{ invoice.gross }}',
            'anchor' => $anchor,
        ])))
        ->assertJsonPath('subject', $expected);
})->with([
    'no anchor' => [null, 'Rechnung {{ invoice.gross }}'],
    'per shoot' => ['per_shoot', 'Rechnung {{ invoice.gross }}'],
    'instalment' => ['installment_due', 'Rechnung '.MergeFieldVocabulary::sample()['invoice']['gross']],
]);

it('previews the invoice of the next open instalment on a real project', function () {
    Storage::fake('local');
    Bus::fake();
    StudioSettings::query()->update(['tax_number' => '22/123/45678']);
    $rule = AutomationRule::factory()->create();
    $project = ClientCase::factory()->create();
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    markBooked($project);
    $plan = PaymentPlan::factory()->for($project, 'case')->create(['total' => 100_000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100_000, 'due_on' => now()->addWeek()->toDateString()]);
    $invoice = app(IssueInvoice::class)($installment)->fresh();
    $query = fn (string $anchor) => http_build_query(['subject' => '{{ invoice.number }}', 'project' => $project->public_id, 'anchor' => $anchor]);
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".$query('installment_due'))
        ->assertJsonPath('subject', $invoice->number);
    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".$query('per_shoot'))
        ->assertJsonPath('subject', '{{ invoice.number }}')
        ->assertJsonPath('samples.invoice', null);
});

it('rejects an unknown field in a test mail', function () {
    Notification::fake();
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->postJson("/einstellungen/automationen/{$rule->public_id}/testmail", [
            'subject' => 'Hallo {{ client.nope }}',
            'body' => 'Rechnung {{ invoice.gross }}',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject' => 'Unbekanntes Feld: client.nope.', 'body' => 'Für diesen Auslöser nicht verfügbar: Bruttobetrag.']);

    Notification::assertNothingSent();
});

it('names subject and body in German on save', function () {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", ['notification_subject' => '', 'notification_body' => ''])
        ->assertSessionHasErrors([
            'notification_subject' => 'Das Feld Betreff muss ausgefüllt werden.',
            'notification_body' => 'Das Feld Text muss ausgefüllt werden.',
        ]);
});

it('offers every anchor with noun, at-phrase and whether it counts down', function (AutomationAnchor $anchor) {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page->where('anchors', fn ($anchors) => collect($anchors)->contains([
            'value' => $anchor->value,
            'label' => $anchor->label(),
            'noun' => $anchor->noun(),
            'at' => $anchor->at(),
            'canBefore' => $anchor->canBefore(),
        ])));
})->with(AutomationAnchor::cases());

it('refuses a countdown to a date that is already past', function (AutomationAnchor $anchor) {
    $rule = AutomationRule::factory()->create();
    $payload = ['name' => 'x', 'anchor' => $anchor->value, 'offset_days' => 3, 'direction' => 'before', 'recipient_type' => 'client',
        'notification_subject' => 's', 'notification_body' => 'b', 'is_active' => true];

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", $payload)
        ->assertSessionHasErrors('direction');
    $this->patch("/einstellungen/automationen/{$rule->public_id}", [...$payload, 'direction' => 'after'])
        ->assertSessionHasNoErrors();
})->with([AutomationAnchor::Delivered, AutomationAnchor::QuoteSent, AutomationAnchor::InquiryCaptured]);

it('sends one clause per event class', function (string $class) {
    $rule = AutomationRule::factory()->event($class)->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page->where('eventClause', fn ($clause) => str_starts_with($clause, 'Sobald ') && str_ends_with($clause, ',')));
})->with(fn () => collect(glob(__DIR__.'/../../../app/*/Events/*.php'))
    ->map(fn (string $file) => 'App\\'.basename(dirname($file, 2)).'\\Events\\'.basename($file, '.php'))
    ->all());

it('leaves the studio logo out and shows the real studio in the field samples', function () {
    StudioSettings::current()->update(['legal_name' => 'Foto Kern GmbH']);
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page->where('mergeFields', fn ($fields) => ! collect($fields)->contains('path', 'studio.logoPath')
            && collect($fields)->firstWhere('path', 'studio.name')['sample'] === 'Foto Kern GmbH'));
});

it('keeps invoice fields as written when the picked project has no open instalment', function () {
    $rule = AutomationRule::factory()->create();
    $project = ClientCase::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query([
            'subject' => '{{ invoice.number }}', 'project' => $project->public_id, 'anchor' => 'installment_due',
        ]))
        ->assertJsonPath('subject', '{{ invoice.number }}')
        ->assertJsonMissingPath('samples.invoice.number');
});

it('returns the resolved value of every available field as samples', function () {
    $rule = AutomationRule::factory()->create();
    $project = ClientCase::factory()->create(['name' => 'Taufe Schulz']);
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau")
        ->assertJsonPath('samples', fn ($samples) => $samples['project.name'] === 'Hochzeit Meier & Kowalski' && ! isset($samples['invoice.gross']));

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query(['project' => $project->public_id, 'anchor' => 'per_shoot']))
        ->assertJsonPath('samples', fn ($samples) => $samples['project.name'] === 'Taufe Schulz');

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?anchor=installment_due")
        ->assertJsonPath('samples', fn ($samples) => $samples['invoice.gross'] === MergeFieldVocabulary::sample()['invoice']['gross']);
});

it('refetches the project list for a project type with the preview', function () {
    $rule = AutomationRule::factory()->create();
    ClientCase::factory()->create(['name' => 'Taufe Schulz', 'type' => 'family']);
    ClientCase::factory()->create(['name' => 'Hochzeit Kern', 'type' => 'wedding']);
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?project_type=wedding")
        ->assertJsonPath('projects', fn ($projects) => collect($projects)->pluck('label')->all() === ['Beispiel', 'Hochzeit Kern']);

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau")
        ->assertJsonMissingPath('projects');
});

it('lists only projects with an open invoice for the instalment anchor', function () {
    Storage::fake('local');
    Bus::fake();
    StudioSettings::query()->update(['tax_number' => '22/123/45678']);
    $rule = AutomationRule::factory()->create();
    $open = ClientCase::factory()->create(['name' => 'Hochzeit Kern']);
    $open->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    markBooked($open);
    $plan = PaymentPlan::factory()->for($open, 'case')->create(['total' => 100_000]);
    app(IssueInvoice::class)(PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100_000, 'due_on' => now()->addWeek()->toDateString()]));
    ClientCase::factory()->create(['name' => 'Taufe Schulz']);
    $owner = staffMember(StaffRole::Admin);
    $labels = fn ($projects) => collect($projects)->pluck('label')->all();

    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?anchor=installment_due")
        ->assertJsonPath('projects', fn ($projects) => $labels($projects) === ['Beispiel', 'Hochzeit Kern']);
    $this->actingAs($owner, 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?anchor=per_shoot")
        ->assertJsonPath('projects', fn ($projects) => collect($labels($projects))->sort()->values()->all() === ['Beispiel', 'Hochzeit Kern', 'Taufe Schulz']);
});

it('puts the project type into the sample', function () {
    $rule = AutomationRule::factory()->create();
    ProjectType::query()->where('key', 'family')->delete();
    ProjectType::factory()->create(['key' => 'family', 'name' => 'Familie']);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query([
            'subject' => '{{ project.type }} / {{ project.name }}', 'project_type' => 'family',
        ]))
        ->assertJsonPath('subject', 'Familie / Familie Meier & Kowalski');
});

it('ignores a picked project outside the given project type', function () {
    $rule = AutomationRule::factory()->create();
    $project = ClientCase::factory()->create(['name' => 'Taufe Schulz', 'type' => 'family']);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->getJson("/einstellungen/automationen/{$rule->public_id}/vorschau?".http_build_query([
            'subject' => '{{ project.name }}', 'project' => $project->public_id, 'project_type' => 'wedding',
        ]))
        ->assertJsonPath('subject', 'Hochzeit Meier & Kowalski');
});

it('names an event rule in German', function () {
    $rule = AutomationRule::factory()->event(CaseBooked::class)->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page->where('rule.eventLabel', 'Projekt gebucht')->where('rule.triggerLabel', 'Sobald ein Projekt gebucht ist'));
});

it('links a draft run to its send screen and counts drafts per rule and in the nav', function () {
    $rule = AutomationRule::factory()->create();
    $project = ClientCase::factory()->create();
    $draft = AutomationRun::create([
        'automation_rule_id' => $rule->id,
        'anchorable_type' => $project->getMorphClass(),
        'anchorable_id' => $project->id,
        'idempotency_key' => 'draft',
        'fired_at' => now(),
        'status' => AutomationRunStatus::AwaitingApproval,
        'subject' => 'Entwurf',
    ]);
    AutomationRun::create([
        'automation_rule_id' => $rule->id,
        'anchorable_type' => $project->getMorphClass(),
        'anchorable_id' => $project->id,
        'idempotency_key' => 'sent',
        'fired_at' => now(),
        'status' => AutomationRunStatus::Sent,
        'subject' => 'Gesendet',
    ]);
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page
            ->where('runs', fn ($runs) => collect($runs)->pluck('draftUrl')->filter()->values()->all() === ["/posteingang/entwuerfe/{$draft->public_id}/senden"]
                && collect($runs)->whereNull('draftUrl')->count() === 1)
            ->where('rule.drafts', 1));

    $this->actingAs($owner, 'staff')
        ->get('/einstellungen/automationen')
        ->assertInertia(fn ($page) => $page->where('rules.0.drafts', 1)->where('nav.drafts', 1));
});

it('names who the preview goes to and whether it becomes a draft', function () {
    $rule = AutomationRule::factory()->create(['recipient_type' => AutomationRecipient::Client]);
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(['name' => 'Anna Berg', 'email' => 'anna@example.test']), CaseContactRole::Primary);
    $owner = staffMember(StaffRole::Admin);
    $url = "/einstellungen/automationen/{$rule->public_id}/vorschau";

    $this->actingAs($owner, 'staff')->getJson($url)
        ->assertJsonPath('draft', true)->assertJsonPath('to', 'Lena Meier');
    $this->actingAs($owner, 'staff')->getJson("{$url}?project={$project->public_id}")
        ->assertJsonPath('to', 'Anna Berg');
    $this->actingAs($owner, 'staff')->getJson("{$url}?recipient=crew")
        ->assertJsonPath('draft', false)->assertJsonPath('to', 'Crew');
});

it('words a date rule like the editor sentence', function (int $days, string $direction, string $label) {
    $rule = AutomationRule::factory()->create(['anchor' => AutomationAnchor::Delivered, 'offset_days' => $days, 'direction' => $direction]);

    expect(AutomationRuleData::fromModel($rule)->triggerLabel)->toBe($label);
})->with([
    [0, 'after', 'Am Tag der Auslieferung'],
    [1, 'after', '1 Tag nach der Auslieferung'],
    [14, 'after', '14 Tage nach der Auslieferung'],
]);

it('words an event rule as its clause without the comma', function () {
    $rule = AutomationRule::factory()->event(CaseBooked::class)->create();

    expect(AutomationRuleData::fromModel($rule)->triggerLabel)->toBe('Sobald ein Projekt gebucht ist');
});

it('names the noun in the message for a countdown to a past date', function () {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", ['name' => 'x', 'anchor' => 'delivered', 'offset_days' => 3, 'direction' => 'before', 'recipient_type' => 'client',
            'notification_subject' => 's', 'notification_body' => 'b', 'is_active' => true])
        ->assertSessionHasErrors(['direction' => 'Vor der Auslieferung nicht möglich.']);
});

it('offers every condition with a clause for after "wenn"', function (AutomationCondition $condition, string $clause) {
    $rule = AutomationRule::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/einstellungen/automationen/{$rule->public_id}")
        ->assertInertia(fn ($page) => $page->where('conditions', fn ($conditions) => collect($conditions)->contains([
            'value' => $condition->value,
            'label' => $condition->label(),
            'clause' => $clause,
        ])));
})->with([
    [AutomationCondition::PaymentOpen, 'die Rate noch offen ist'],
    [AutomationCondition::QuestionnaireOpen, 'der Fragebogen offen ist'],
    [AutomationCondition::GalleryUnopened, 'die Galerie noch ungeöffnet ist'],
]);

it('creates a rule with the starting state in the studio register', function (string $register, string $greeting) {
    StudioSettings::current()->update(['client_register' => $register]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')->post('/einstellungen/automationen')->assertRedirect();

    $rule = AutomationRule::query()->latest('id')->first();
    expect($rule->anchor)->toBe(AutomationAnchor::PrimaryShoot)
        ->and($rule->offset_days)->toBe(7)
        ->and($rule->direction->value)->toBe('before')
        ->and($rule->recipient_type)->toBe(AutomationRecipient::Client)
        ->and($rule->action)->toBe(AutomationAction::MailDraft)
        ->and($rule->is_active)->toBeFalse()
        ->and($rule->notification_subject)->toBe('Wir sehen uns am {{ shoot.date }}')
        ->and($rule->notification_body)->toStartWith($greeting.' {{ contact.name }},')->toContain('am {{ shoot.date }} ist es so weit');
})->with([
    ['formal', 'Guten Tag'],
    ['informal', 'Hallo'],
]);

it('puts every anchor and every event in a group with a position', function () {
    foreach (AutomationAnchor::cases() as $anchor) {
        expect($anchor->group())->not->toBe('')->and($anchor->order())->toBeGreaterThan(0);
    }

    foreach (glob(__DIR__.'/../../../app/*/Events/*.php') as $file) {
        $event = __('settings.event_groups.'.basename($file, '.php'));
        expect($event)->toBeArray()->toHaveKeys(['group', 'order']);
    }

    $rule = AutomationRule::factory()->event(CaseBooked::class)->create();
    expect(AutomationRuleData::fromModel($rule))->group->toBe('Buchung')->order->toBe(41500);
});

it('files a rule that waits after a shoot under Nachher', function () {
    $after = AutomationRule::factory()->create(['anchor' => AutomationAnchor::PrimaryShoot, 'direction' => 'after', 'offset_days' => 3]);
    $before = AutomationRule::factory()->create(['anchor' => AutomationAnchor::PrimaryShoot, 'direction' => 'before', 'offset_days' => 3]);
    $on = AutomationRule::factory()->create(['anchor' => AutomationAnchor::PerShoot, 'direction' => 'after', 'offset_days' => 0]);

    expect(AutomationRuleData::fromModel($after))->group->toBe('Nachher')->order->toBe(90503)
        ->and(AutomationRuleData::fromModel($before))->group->toBe('Shooting')->order->toBe(70497)
        ->and(AutomationRuleData::fromModel($on))->group->toBe('Shooting')->order->toBe(60500);
});
