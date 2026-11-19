<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectField;
use App\Crm\Models\Shoot;
use App\Crm\Notifications\Studio\InquiryReceived;
use App\Enums\Capability;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Integration\Mcp\ApiTool;
use App\Integration\Mcp\CrmServer;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Actions\UpdateStudioSetting;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request as McpRequest;
use Opis\JsonSchema\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

function mcpTool(string $name): ApiTool
{
    return new ApiTool(collect(CrmServer::definitions())->firstWhere('name', $name));
}

/**
 * The bearer a tool call travels with. Over HTTP it is whichever token
 * opened the MCP session, and ApiCall reads it off the request — so a test
 * puts it there rather than on the sub-request it cannot see.
 */
function mcpBearer(Capability ...$scopes): ApiToken
{
    $token = ApiToken::factory()->withScopes(...$scopes)->create();

    request()->headers->set('Authorization', 'Bearer '.bearerToken($token));

    return $token;
}

it('describes every tool it registers', function () {
    $tools = collect(CrmServer::definitions())->map(fn ($definition) => new ApiTool($definition));

    expect($tools->map->name()->duplicates())->toBeEmpty();

    $tools->each(function (ApiTool $tool) {
        expect($tool->toArray())
            ->toHaveKeys(['name', 'title', 'description', 'inputSchema'])
            ->and($tool->description())->not->toBeEmpty();
    });
});

it('answers a read tool from the api surface', function () {
    mcpBearer(Capability::ManageLeads);

    Lead::factory()->for(ClientCase::factory(), 'case')->create(['stage' => LeadStage::New]);

    CrmServer::tool(mcpTool('pipeline_snapshot'))
        ->assertOk()
        ->assertSee('"stage":"new"');
});

it('refuses a tool the token holds no scope for', function () {
    mcpBearer(Capability::ManageContacts);

    CrmServer::tool(mcpTool('pipeline_snapshot'))
        ->assertHasErrors()
        ->assertSee('403');
});

it('reports what the api surface refused', function () {
    mcpBearer(Capability::ViewAllCases);

    CrmServer::tool(mcpTool('get_case'), ['case' => 'nobody'])
        ->assertHasErrors()
        ->assertSee('404');
});

it('writes through the api surface', function () {
    User::factory()->create();
    Notification::fake();
    mcpBearer(Capability::ManageLeads);

    CrmServer::tool(mcpTool('capture_lead'), captureArguments())->assertOk();

    expect(Lead::count())->toBe(1);
    Notification::assertSentTimes(InquiryReceived::class, 1);
});

/**
 * The idempotency key is built out of the arguments, so a client that repeats
 * a call — the retry of a dropped answer — writes once. EnsureIdempotentWrite
 * replays the first answer for the second call.
 */
it('writes once when the same call arrives twice', function () {
    User::factory()->create();
    mcpBearer(Capability::ManageLeads);

    CrmServer::tool(mcpTool('capture_lead'), captureArguments())->assertOk();
    CrmServer::tool(mcpTool('capture_lead'), captureArguments())->assertOk();

    expect(Lead::count())->toBe(1);
});

it('keeps the studio quiet for a silent write', function () {
    User::factory()->create();
    Notification::fake();
    mcpBearer(Capability::ManageLeads);

    CrmServer::tool(mcpTool('capture_lead'), [...captureArguments(), 'silent' => true])->assertOk();

    expect(Lead::count())->toBe(1);
    Notification::assertNothingSent();
});

/**
 * The same invariant NoInternalIdsTest holds for an Inertia prop and a JSON
 * body, on the one shape neither reaches: what a tool hands back.
 */
it('never puts an integer identifier into a tool result', function () {
    User::factory()->create();
    mcpBearer(Capability::ManageLeads);

    $result = app()->call([mcpTool('capture_lead'), 'handle'], ['request' => new McpRequest(captureArguments())]);

    expect(integerIdPaths(json_decode((string) $result->content(), true)))->toBe([]);
});

/** Over stdio there is no request to take a bearer from — see ApiCall. */
it('falls back to the token the process was started with', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    config(['services.mcp.token' => bearerToken($token)]);

    Lead::factory()->for(ClientCase::factory(), 'case')->create(['stage' => LeadStage::New]);

    CrmServer::tool(mcpTool('pipeline_snapshot'))
        ->assertOk()
        ->assertSee('"stage":"new"');
});

it('turns a client away without a bearer the api surface accepts', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

it('says where tokens for this resource come from', function () {
    config(['services.mcp.url' => 'https://api.studio.test/mcp', 'services.admin.url' => 'https://admin.studio.test']);

    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertExactJson([
            'resource' => 'https://api.studio.test/mcp',
            'authorization_servers' => ['https://admin.studio.test'],
            'bearer_methods_supported' => ['header'],
        ]);
});

function captureArguments(): array
{
    return [
        'contact_name' => 'Nina Voss',
        'contact_email' => 'nina@example.test',
        'project_name' => 'Voss — Portrait',
        'type' => 'portrait',
        'source' => 'website',
        'captured_via' => 'other',
    ];
}

it('returns an empty field map as an object', function () {
    mcpBearer(Capability::ViewAllCases);
    $case = ClientCase::factory()->booked()->create(['field_values' => []]);

    $detail = app()->call([mcpTool('get_case'), 'handle'], ['request' => new McpRequest(['case' => $case->public_id])]);
    $projects = app()->call([mcpTool('list_projects'), 'handle'], ['request' => new McpRequest([])]);

    expect(mcpTool('get_case')->toArray()['outputSchema']['properties']['case']['properties']['fields']['type'])->toBe('object')
        ->and(json_decode(json_encode($detail->getStructuredContent()))->case->fields)->toBeInstanceOf(stdClass::class)
        ->and(json_decode(json_encode($projects->getStructuredContent()))->projects[0]->fields)->toBeInstanceOf(stdClass::class);
});

/** What the client sees: the structured content after the wire, against the schema the tool declared. */
function validateAgainstOutputSchema(string $tool, array $arguments = []): void
{
    $response = app()->call([mcpTool($tool), 'handle'], ['request' => new McpRequest($arguments)]);

    $schema = json_decode(json_encode(mcpTool($tool)->toArray()['outputSchema']));
    $result = (new Validator)->validate(json_decode(json_encode($response->getStructuredContent())), $schema);

    expect($result->isValid())->toBeTrue(json_encode($result->error()?->message()));
}

function seedFullCase(): ClientCase
{
    Storage::fake('local');
    $case = ClientCase::factory()->booked()->create(['field_values' => ['gaeste' => 80]]);
    ProjectField::factory()->create();
    $case->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    Lead::factory()->for($case, 'case')->create();
    Shoot::factory()->for($case, 'case')->create();
    QuoteItem::factory()->for(Quote::factory()->for($case, 'case')->create(), 'quote')->create();
    InternalNote::factory()->for($case, 'case')->create();
    PaymentInstallment::factory()->for(PaymentPlan::factory()->for($case, 'case')->create(['total' => 100000]), 'plan')->create();
    PackageItem::factory()->for(Package::factory()->create(), 'package')->create();

    $form = FormInstance::factory()->for($case, 'case')->create();
    FormInstanceField::factory()->for($form, 'formInstance')->create(['label' => 'Wer kommt?', 'help' => 'Auch Kinder.', 'sort_order' => 0])->answer('Oma');
    FormInstanceField::factory()->for($form, 'formInstance')->select(['Ja', 'Nein'])->create(['label' => 'Fotos?', 'sort_order' => 1]);
    FormInstanceField::factory()->for($form, 'formInstance')->create([
        'label' => 'Gruppen', 'type' => 'group_photos', 'help' => 'Wählt die Gruppen.', 'options' => ['suggestions' => ['Eltern'], 'minutesPerGroup' => 2], 'sort_order' => 2,
    ]);
    FormInstanceField::factory()->for($form, 'formInstance')->create([
        'label' => 'Plan', 'type' => 'file', 'options' => ['formats' => ['pdf'], 'maxSize' => 5, 'multiple' => false], 'sort_order' => 3,
    ])->attachFile(UploadedFile::fake()->create('ablauf.pdf', 10, 'application/pdf'));

    $gallery = Gallery::factory()->create();
    $gallery->sets()->first()->assets()->create(['gallery_id' => $gallery->id, 'key' => 'originals/a/one.jpg', 'filename' => 'one.jpg', 'status' => 'ready']);
    AssetFavorite::query()->create(['gallery_version_id' => $gallery->publish()->id, 'asset_key' => 'originals/a/one.jpg', 'guest_identifier' => 'guest-1']);

    return $case;
}

it('validates get_case against its output schema with and without field values', function () {
    mcpBearer(Capability::ViewAllCases);

    validateAgainstOutputSchema('get_case', ['case' => ClientCase::factory()->booked()->create(['field_values' => []])->public_id]);
    validateAgainstOutputSchema('get_case', ['case' => seedFullCase()->public_id]);
});

/** @return array<string, mixed> */
function formArguments(string $tool): array
{
    $form = FormInstance::query()->with('fields')->first();
    $fields = $form->fields->map(fn (FormInstanceField $field) => [
        'id' => $field->public_id, 'label' => $field->label, 'type' => $field->type->value, 'required' => false, 'help' => $field->help,
        'options' => $field->type->value === 'select' ? $field->options : null,
    ])->all();

    return match ($tool) {
        'update_form' => ['form' => $form->public_id, 'name' => $form->name, 'fields' => $fields],
        'create_form_template' => ['name' => 'Ablauf', 'fields' => [['label' => 'Wer kommt?', 'type' => 'text', 'required' => false]]],
        'update_form_template' => ['template' => FormTemplate::factory()->create()->public_id, 'fields' => []],
        'share_form' => ['form' => $form->public_id, 'shared' => true],
        'close_form' => ['form' => $form->public_id, 'closed' => true],
        'answer_form_field' => ['field' => $form->fields->first()->public_id, 'value' => 'Oma und Opa'],
        default => [],
    };
}

it('validates every tool with an output schema against it', function (string $tool) {
    mcpBearer(
        Capability::ViewAllCases, Capability::ManageLeads, Capability::ManageContacts, Capability::ManageNotes,
        Capability::ManageContracts, Capability::ManagePackages, Capability::ManageQuotes, Capability::ManageShoots,
        Capability::ManagePortalAccess, Capability::ManageFormTemplates,
    );
    $case = seedFullCase();

    validateAgainstOutputSchema($tool, ['case' => $case->public_id, 'gallery' => Gallery::query()->value('public_id'), ...formArguments($tool)]);
})->with(fn () => collect(CrmServer::definitions())->filter(fn ($definition) => $definition->outputSchema !== null)->pluck('name')->all());

it('issues an invoice from positions and a credit note through the invoice tools', function () {
    Bus::fake();
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    mcpBearer(Capability::ManageContracts);

    $contact = Contact::factory()->create();

    CrmServer::tool(mcpTool('issue_invoice_from_items'), [
        'contact_id' => $contact->public_id,
        'due_on' => '2026-12-01',
        'items' => [['title' => 'Abzüge', 'quantity' => 1, 'unit_price' => 2_000]],
    ])->assertOk()->assertSee('Abzüge');

    CrmServer::tool(mcpTool('list_invoices'))->assertOk()->assertSee('RE-');

    $invoice = creditableInstallment()->invoice;

    CrmServer::tool(mcpTool('issue_credit_note'), ['invoice' => $invoice->public_id, 'amount' => 5_000, 'reason' => 'Nachlass'])
        ->assertOk()
        ->assertSee('5000');

    CrmServer::tool(mcpTool('list_credit_notes'))->assertOk()->assertSee($invoice->number);
});

it('refuses the invoice tools to a token without the contracts scope', function () {
    mcpBearer(Capability::ManageQuotes);

    CrmServer::tool(mcpTool('list_invoices'))->assertSee('403');
});

it('creates, lists and updates a form template without touching a form seeded from it', function () {
    mcpBearer(Capability::ManageFormTemplates, Capability::ViewAllCases);
    $case = ClientCase::factory()->booked()->create();

    $created = app()->call([mcpTool('create_form_template'), 'handle'], ['request' => new McpRequest([
        'name' => 'Ablauf', 'description' => 'Nur intern', 'project_type' => 'wedding',
        'fields' => [
            ['label' => 'Wer kommt?', 'type' => 'text', 'required' => true, 'help' => 'Auch Kinder.'],
            ['label' => 'Fotos?', 'type' => 'select', 'required' => false, 'options' => ['Ja', 'Nein']],
            ['label' => 'Alt', 'type' => 'text', 'required' => false],
        ],
    ])])->getStructuredContent()['template'];

    expect($created['id'])->not->toBeEmpty()
        ->and(collect($created['fields'])->pluck('id')->filter())->toHaveCount(3)
        ->and($created['description'])->toBe('Nur intern');

    $listed = app()->call([mcpTool('list_form_templates'), 'handle'], ['request' => new McpRequest([])])->getStructuredContent()['templates'];
    expect($listed)->toHaveCount(1)->and($listed[0]['fieldCount'])->toBe(3)->and($listed[0]['projectType'])->toBe('wedding');

    $form = app()->call([mcpTool('create_form'), 'handle'], ['request' => new McpRequest(['case' => $case->public_id, 'template' => $created['id']])])->getStructuredContent()['form'];
    expect(collect($form['fields'])->pluck('label')->all())->toBe(['Wer kommt?', 'Fotos?', 'Alt'])
        ->and($form['fields'][0]['help'])->toBe('Auch Kinder.');

    $kept = $created['fields'][0];
    $updated = app()->call([mcpTool('update_form_template'), 'handle'], ['request' => new McpRequest([
        'template' => $created['id'],
        'fields' => [
            ['label' => 'Neu', 'type' => 'boolean', 'required' => false],
            ['id' => $kept['id'], 'label' => 'Wer kommt mit?', 'type' => 'text', 'required' => true],
        ],
    ])])->getStructuredContent()['template'];

    expect(collect($updated['fields'])->pluck('label')->all())->toBe(['Neu', 'Wer kommt mit?'])
        ->and($updated['fields'][1]['id'])->toBe($kept['id'])
        ->and(FormInstance::query()->with('fields')->first()->fields->pluck('label')->all())->toBe(['Wer kommt?', 'Fotos?', 'Alt']);
});

it('refuses a template name already taken, whatever its case', function () {
    mcpBearer(Capability::ManageFormTemplates);
    FormTemplate::factory()->create(['name' => 'Ablauf']);

    CrmServer::tool(mcpTool('create_form_template'), ['name' => 'ABLAUF'])->assertHasErrors();

    $other = FormTemplate::factory()->create(['name' => 'Anders']);
    CrmServer::tool(mcpTool('update_form_template'), ['template' => $other->public_id, 'name' => 'ablauf', 'fields' => []])->assertHasErrors();
    CrmServer::tool(mcpTool('update_form_template'), ['template' => $other->public_id, 'name' => 'ANDERS', 'fields' => []])->assertOk();
});

it('takes options on select template fields only and keeps a file field\'s configuration', function () {
    mcpBearer(Capability::ManageFormTemplates);
    $field = fn (string $type, array $options) => [['label' => 'Frage', 'type' => $type, 'required' => false, 'options' => $options]];

    CrmServer::tool(mcpTool('create_form_template'), ['name' => 'A', 'fields' => $field('select', ['Ja', 'Nein'])])->assertOk();
    CrmServer::tool(mcpTool('create_form_template'), ['name' => 'B', 'fields' => $field('text', ['Ja'])])->assertHasErrors();
    CrmServer::tool(mcpTool('create_form_template'), ['name' => 'C', 'fields' => $field('file', ['formats' => ['pdf']])])->assertHasErrors();

    $template = FormTemplate::factory()->create(['name' => 'D']);
    $file = $template->fields()->create(['label' => 'Ablaufplan', 'type' => 'file', 'required' => false, 'options' => ['formats' => ['pdf']], 'sort_order' => 0]);

    $listed = app()->call([mcpTool('list_form_templates'), 'handle'], ['request' => new McpRequest([])])->getStructuredContent()['templates'];
    $sent = collect($listed)->firstWhere('name', 'D')['fields'][0];
    expect($sent['options'])->toBeNull();

    CrmServer::tool(mcpTool('update_form_template'), ['template' => $template->public_id, 'fields' => [
        ['id' => $sent['id'], 'label' => 'Ablauf', 'type' => 'file', 'required' => false],
    ]])->assertOk();

    expect($file->fresh()->options)->toBe(['formats' => ['pdf']]);
});
