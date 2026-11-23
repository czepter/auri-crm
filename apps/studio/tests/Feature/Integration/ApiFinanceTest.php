<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Finance\Actions\LockFiscalYear;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Integration\Mcp\ApiTool;
use App\Integration\Mcp\CrmServer;
use App\Integration\Mcp\Tools\FinanceTools;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->travelTo('2026-11-22 10:00:00');
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageFinances)->create()));
});

function financeDraftBody(array $overrides = []): array
{
    return ['vendor' => 'Lichtwerk Software Ltd.', 'gross' => 2380, 'vat_rate' => 1900, 'invoice_date' => '2026-02-01', ...$overrides];
}

it('offers exactly the nine tools and routes that make drafts and read', function () {
    $tools = collect(FinanceTools::definitions())->pluck('name')->all();
    $routes = collect(Route::getRoutes()->getRoutes())->map->getName()->filter(fn ($name) => str_starts_with((string) $name, 'api.finance.'))->sort()->values()->all();

    expect($tools)->toBe(['request_document_upload', 'create_expense_draft', 'update_expense_draft', 'suggest_asset', 'list_expenses', 'find_duplicate_expense', 'list_expense_categories', 'list_assets', 'get_euer'])
        ->and(collect(CrmServer::definitions())->pluck('name')->intersect($tools)->count())->toBe(9)
        ->and($routes)->toBe([
            'api.finance.assets.index', 'api.finance.categories.index', 'api.finance.documents.content', 'api.finance.documents.uploads',
            'api.finance.euer.show', 'api.finance.expenses.asset', 'api.finance.expenses.duplicates', 'api.finance.expenses.index',
            'api.finance.expenses.store', 'api.finance.expenses.update',
        ]);
});

it('refuses every finance route to a token without the scope and to a manager’s staff token', function () {
    $expense = Expense::factory()->draft()->create();
    $manager = staffMember(StaffRole::Manager)->createToken('mcp', array_map(fn (Capability $c) => $c->value, StaffRole::Manager->capabilities()))->plainTextToken;
    $other = bearerToken(ApiToken::factory()->withScopes(Capability::ManageContracts)->create());

    foreach (['Bearer '.$other, 'Bearer '.$manager] as $bearer) {
        $this->withHeader('Authorization', $bearer);
        foreach (['/api/finance/expenses', '/api/finance/expenses/duplicates', '/api/finance/expense-categories', '/api/finance/assets', '/api/finance/euer/2026'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->withHeader('Idempotency-Key', 'k-'.md5($bearer));
        $this->postJson('/api/finance/expenses', financeDraftBody())->assertForbidden();
        $this->postJson('/api/finance/documents/uploads', ['filename' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 10])->assertForbidden();
        $this->patchJson("/api/finance/expenses/{$expense->public_id}", ['note' => 'x'])->assertForbidden();
        $this->postJson("/api/finance/expenses/{$expense->public_id}/asset")->assertForbidden();
    }
});

it('always creates a draft, never a status, and leaves assets to suggest_asset', function () {
    $this->withHeader('Idempotency-Key', 'c1')->postJson('/api/finance/expenses', financeDraftBody(['status' => 'booked', 'asset' => ['name' => 'x']]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.net', 2000)
        ->assertJsonPath('data.vat', 380)
        ->assertJsonPath('data.grossEur', 2380)
        ->assertJsonPath('data.asset', null);

    expect(integerIdPaths(Expense::query()->first()->toArray()))->toBe([]);
});

it('creates once when the same call repeats', function () {
    $this->withHeader('Idempotency-Key', 'same')->postJson('/api/finance/expenses', financeDraftBody())->assertCreated();
    $this->withHeader('Idempotency-Key', 'same')->postJson('/api/finance/expenses', financeDraftBody())->assertCreated();

    expect(Expense::query()->count())->toBe(1);
});

it('refuses a draft dated in a locked year', function () {
    FiscalYear::factory()->locked()->create(['year' => 2025]);

    $this->withHeader('Idempotency-Key', 'l1')->postJson('/api/finance/expenses', financeDraftBody(['paid_at' => '2025-06-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('paid_at');
});

it('updates a draft, re-deriving net and VAT from a new gross, and refuses a booked or ignored one', function () {
    $draft = Expense::factory()->draft()->create(['net' => 10000, 'vat' => 1900, 'gross' => 11900, 'gross_eur' => 11900]);

    $this->withHeader('Idempotency-Key', 'u1')->patchJson("/api/finance/expenses/{$draft->public_id}", ['gross' => 5950, 'note' => 'Abo'])
        ->assertOk()
        ->assertJsonPath('data.net', 5000)
        ->assertJsonPath('data.vat', 950)
        ->assertJsonPath('data.note', 'Abo')
        ->assertJsonPath('data.vendor', 'Nordhost Hosting GmbH');

    foreach (['booked', 'ignored'] as $i => $status) {
        $other = Expense::factory()->create(['status' => $status]);
        $this->withHeader('Idempotency-Key', "u-$i")->patchJson("/api/finance/expenses/{$other->public_id}", ['note' => 'x'])->assertUnprocessable();
        expect($other->fresh()->note)->toBeNull();
    }
});

it('suggests a GWG up to the limit and a linear asset above it, and refuses a booked expense', function () {
    $small = Expense::factory()->draft()->create(['net' => 64000, 'vat' => 12160, 'gross' => 76160, 'gross_eur' => 76160, 'invoice_date' => '2026-05-03']);
    $big = Expense::factory()->draft()->create(['net' => 145000, 'vat' => 27550, 'gross' => 172550, 'gross_eur' => 172550, 'invoice_date' => '2026-04-10']);
    $booked = Expense::factory()->create();

    $this->withHeader('Idempotency-Key', 's1')->postJson("/api/finance/expenses/{$small->public_id}/asset")
        ->assertOk()
        ->assertJsonPath('data.asset.method', 'gwg')
        ->assertJsonPath('data.asset.costNet', 64000)
        ->assertJsonPath('data.asset.inventoryNo', 'INV-26-001');

    $this->withHeader('Idempotency-Key', 's2')->postJson("/api/finance/expenses/{$big->public_id}/asset")->assertUnprocessable()->assertJsonValidationErrors('useful_life_months');
    $this->withHeader('Idempotency-Key', 's3')->postJson("/api/finance/expenses/{$big->public_id}/asset", ['afa_category' => 'computer_software'])
        ->assertOk()->assertJsonPath('data.asset.usefulLifeMonths', 12)->assertJsonPath('data.asset.method', 'linear');
    $this->withHeader('Idempotency-Key', 's4')->postJson("/api/finance/expenses/{$small->public_id}/asset", ['name' => 'Objektiv'])
        ->assertOk()->assertJsonPath('data.asset.name', 'Objektiv');
    $this->withHeader('Idempotency-Key', 's5')->postJson("/api/finance/expenses/{$booked->public_id}/asset")->assertUnprocessable();

    expect(FixedAsset::query()->count())->toBe(2);
});

it('finds duplicates by receipt hash, by invoice number of the same vendor and by vendor, gross and date', function () {
    $document = FinanceDocument::factory()->create(['sha256' => str_repeat('a', 64)]);
    $one = Expense::factory()->create(['vendor' => 'Lichtwerk Software Ltd.', 'invoice_no' => 'LW-041', 'gross' => 2380, 'net' => 2000, 'vat' => 380, 'invoice_date' => '2026-02-01']);
    $one->documents()->attach($document);

    $ids = fn (array $query) => array_column($this->getJson('/api/finance/expenses/duplicates?'.http_build_query($query))->assertOk()->json('data'), 'id');

    expect($ids(['sha256' => str_repeat('a', 64)]))->toBe([$one->public_id])
        ->and($ids(['vendor' => 'lichtwerk software ltd.', 'invoice_no' => 'lw-041']))->toBe([$one->public_id])
        ->and($ids(['vendor' => 'Lichtwerk Software Ltd.', 'gross' => 2380, 'invoice_date' => '2026-02-01']))->toBe([$one->public_id])
        ->and($ids(['vendor' => 'Anderer', 'invoice_no' => 'LW-041']))->toBe([])
        ->and($ids(['sha256' => str_repeat('b', 64)]))->toBe([])
        ->and($ids([]))->toBe([]);
});

it('lists expenses, categories and assets', function () {
    Expense::factory()->create(['vendor' => 'A', 'paid_at' => '2026-02-01']);
    Expense::factory()->draft()->create(['vendor' => 'B', 'paid_at' => '2025-02-01']);
    FixedAsset::factory()->create(['acquired_at' => '2026-03-10']);
    $laptop = Expense::factory()->create();
    $laptop->asset()->associate(FixedAsset::factory()->create(['acquired_at' => '2026-04-10', 'cost_net' => 145000, 'cost_gross' => 172550, 'useful_life_months' => 12]))->save();

    expect(array_column($this->getJson('/api/finance/expenses?year=2026')->assertOk()->json('data'), 'vendor'))->toContain('A')->not->toContain('B')
        ->and(array_column($this->getJson('/api/finance/expenses?status=draft&without_receipt=true')->json('data'), 'vendor'))->toBe(['B'])
        ->and($this->getJson('/api/finance/expense-categories')->json('data'))->toHaveCount(10)
        ->and($this->getJson('/api/finance/assets?year=2026')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/finance/assets?year=2026')->json('data.0.bookValue'))->toBe(36250)
        ->and($this->getJson('/api/finance/assets?as_of=2026-06-30')->json('data.0.bookValue'))->toBe(108750);
});

it('answers get_euer with the figures of the admin page', function () {
    Expense::factory()->create(['paid_at' => '2026-02-01', 'category_id' => ExpenseCategory::query()->where('key', 'hosting_domains')->value('id')]);

    $api = $this->getJson('/api/finance/euer/2026')->assertOk()->json('data');

    useSurface(Surface::ADMIN);
    $page = $this->actingAs(staffMember(), 'staff');
    $report = props('/finanzen/euer?jahr=2026')['report'];

    expect($api)->toBe($report)
        ->and($api['paidInputVat'])->toBe(1900);
});

it('returns the stored snapshot of a locked year', function () {
    Expense::factory()->create(['paid_at' => '2025-02-01']);
    app(LockFiscalYear::class)(2025);
    Expense::query()->toBase()->update(['gross_eur' => 1]);

    expect($this->getJson('/api/finance/euer/2025')->assertOk()->json('data.lockedAt'))->toBe('2026-11-22')
        ->and($this->getJson('/api/finance/euer/2025')->json('data.paidInputVat'))->toBe(1900);
});

it('runs the tools through the api surface and cannot reach past drafts', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageFinances)->create();
    request()->headers->set('Authorization', 'Bearer '.bearerToken($token));
    $tool = fn (string $name) => new ApiTool(collect(CrmServer::definitions())->firstWhere('name', $name));

    CrmServer::tool($tool('create_expense_draft'), financeDraftBody())->assertOk()->assertSee('"status":"draft"');
    CrmServer::tool($tool('list_expense_categories'))->assertOk()->assertSee('hosting_domains');
    CrmServer::tool($tool('get_euer'), ['year' => 2026])->assertOk()->assertSee('"year":2026');

    expect(Expense::query()->sole()->status->value)->toBe('draft');
});
