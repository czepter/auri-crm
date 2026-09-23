<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Enums\Capability;
use App\Integration\Mcp\ApiTool;
use App\Integration\Mcp\CrmServer;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Notifications\Studio\InquiryReceived;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Mcp\Request as McpRequest;

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
