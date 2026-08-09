<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Models\Project;
use App\Models\AuditLog;
use App\Models\User;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function quoteOwner(): User
{
    return User::factory()->withMfa()->create();
}

// --- QuoteItem: the Enthaltene Leistung distinction --------------------

it('treats a null unit_price as included, and a zero unit_price as a real free line', function () {
    $included = QuoteItem::factory()->included()->make(['quantity' => 2]);
    $free = QuoteItem::factory()->make(['quantity' => 2, 'unit_price' => 0]);

    expect($included->isIncluded())->toBeTrue()
        ->and($included->lineTotal())->toBe(0)
        ->and($free->isIncluded())->toBeFalse()
        ->and($free->lineTotal())->toBe(0)
        ->and($free->unit_price)->toBe(0)
        ->and($included->unit_price)->toBeNull();
});

it('computes a priced line total as quantity times unit_price', function () {
    $item = QuoteItem::factory()->make(['quantity' => 3, 'unit_price' => 15000]);

    expect($item->lineTotal())->toBe(45000);
});

// --- Quote: total excludes included lines -------------------------------

it('recalculates the total from priced lines only, excluding every included line', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['quantity' => 1, 'unit_price' => 440000]);
    QuoteItem::factory()->for($quote)->create(['quantity' => 2, 'unit_price' => 30000]);
    QuoteItem::factory()->for($quote)->included()->create(['quantity' => 1]);

    $quote->recalculateTotal();

    expect($quote->fresh()->total)->toBe(500000);
});

it('totals a quote with no priced lines at all, included or otherwise, as zero', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->included()->create();

    $quote->recalculateTotal();

    expect($quote->fresh()->total)->toBe(0);
});

it('defaults a new quote to draft, EUR, and a zero total', function () {
    $quote = Quote::factory()->create();

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->currency)->toBe('EUR')
        ->and($quote->total)->toBe(0);
});

// --- SeedQuoteFromPackage: the deep, disconnected copy ------------------

it('seeds a quote\'s items from a package\'s current items, matching them exactly', function () {
    $package = Package::factory()->create();
    PackageItem::factory()->for($package)->create([
        'title' => 'Fotograf ganztägig',
        'description' => 'Inklusive Anfahrt',
        'quantity' => 1,
        'unit_price' => 440000,
        'sort_order' => 0,
    ]);
    PackageItem::factory()->for($package)->create([
        'title' => 'USB-Box in Holz',
        'quantity' => 1,
        'unit_price' => null,
        'sort_order' => 1,
    ]);
    $quote = Quote::factory()->create();

    app(SeedQuoteFromPackage::class)($quote, $package);

    expect($quote->items)->toHaveCount(2)
        ->and($quote->items->first()->title)->toBe('Fotograf ganztägig')
        ->and($quote->items->first()->unit_price)->toBe(440000)
        ->and($quote->items->last()->title)->toBe('USB-Box in Holz')
        ->and($quote->items->last()->unit_price)->toBeNull()
        ->and($quote->items->last()->isIncluded())->toBeTrue()
        ->and($quote->fresh()->total)->toBe(440000);
});

it('leaves a quote already seeded from a package untouched when the package is edited or deactivated afterwards', function () {
    // The acceptance criterion, proven directly: Package::copyItems() hands
    // back a disconnected copy with no id and no package_id, so nothing
    // written into the quote can be reached by a later write to the package.
    $package = Package::factory()->create(['name' => 'Hochzeit Basis']);
    PackageItem::factory()->for($package)->create(['title' => 'Fotograf ganztägig', 'unit_price' => 440000]);
    $quote = Quote::factory()->create();

    app(SeedQuoteFromPackage::class)($quote, $package);

    $seededTitle = $quote->items->sole()->title;
    $seededPrice = $quote->items->sole()->unit_price;

    $package->update(['name' => 'Umbenannt', 'is_active' => false]);
    $package->items()->first()->update(['title' => 'Retitled', 'unit_price' => 1]);

    $quote->refresh();

    expect($quote->items)->toHaveCount(1)
        ->and($quote->items->sole()->title)->toBe($seededTitle)
        ->and($quote->items->sole()->unit_price)->toBe($seededPrice)
        ->and($quote->items->sole()->title)->not->toBe('Retitled');
});

it('seeds nothing from a package with no items, and totals zero', function () {
    $package = Package::factory()->create();
    $quote = Quote::factory()->create();

    app(SeedQuoteFromPackage::class)($quote, $package);

    expect($quote->items)->toHaveCount(0)
        ->and($quote->fresh()->total)->toBe(0);
});

// --- QuoteController: creating and editing a draft ----------------------

it('creates a blank draft quote for a project, and logs it', function () {
    $user = quoteOwner();
    $project = Project::factory()->create();

    $this->actingAs($user, 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['valid_until' => '2026-12-31'])
        ->assertRedirect();

    $quote = $project->quotes()->sole();

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->items)->toHaveCount(0)
        ->and($quote->valid_until->format('Y-m-d'))->toBe('2026-12-31');

    $row = AuditLog::where('event', 'quote.created')->sole();

    expect($row->actor_id)->toBe($user->id)
        ->and($row->subject)->toBe($quote->public_id);
});

it('creates a quote seeded from a package via the store action', function () {
    $project = Project::factory()->create();
    $package = Package::factory()->create();
    PackageItem::factory()->for($package)->create(['unit_price' => 200000]);
    PackageItem::factory()->for($package)->included()->create();

    $this->actingAs(quoteOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['package' => $package->public_id])
        ->assertRedirect();

    $quote = $project->quotes()->sole();

    expect($quote->items)->toHaveCount(2)
        ->and($quote->total)->toBe(200000);
});

it('refuses to seed from a deactivated package', function () {
    $project = Project::factory()->create();
    $package = Package::factory()->inactive()->create();

    $this->actingAs(quoteOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['package' => $package->public_id])
        ->assertSessionHasErrors();

    expect($project->quotes()->count())->toBe(0);
});

it('replaces a draft quote\'s items wholesale on update, recalculates the total, and logs it', function () {
    $user = quoteOwner();
    $quote = Quote::factory()->create();
    $original = QuoteItem::factory()->for($quote)->create(['title' => 'Alte Position']);

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'valid_until' => '2027-01-15',
        'items' => [
            ['title' => 'Hochzeitsreportage', 'description' => null, 'quantity' => 1, 'unit_price' => 440000],
            ['title' => 'Enthaltene Leistung', 'description' => null, 'quantity' => 1, 'unit_price' => null],
        ],
    ])->assertRedirect();

    $quote->refresh();

    expect($quote->items)->toHaveCount(2)
        ->and(QuoteItem::find($original->id))->toBeNull()
        ->and($quote->items->first()->title)->toBe('Hochzeitsreportage')
        ->and($quote->items->last()->unit_price)->toBeNull()
        ->and($quote->total)->toBe(440000)
        ->and($quote->valid_until->format('Y-m-d'))->toBe('2027-01-15');

    expect(AuditLog::where('event', 'quote.updated')->where('subject', $quote->public_id)->exists())->toBeTrue();
});

// --- The screen: included lines render without a price, and stay out of the total ---

it('renders a project\'s quotes with included lines carrying no price, and excludes them from the total', function () {
    $project = Project::factory()->create();
    $quote = Quote::factory()->for($project)->create();
    QuoteItem::factory()->for($quote)->create(['title' => 'Hochzeitsreportage', 'quantity' => 1, 'unit_price' => 440000]);
    QuoteItem::factory()->for($quote)->included()->create(['title' => 'USB-Box in Holz']);
    $quote->recalculateTotal();

    $props = $this->actingAs(quoteOwner(), 'staff')
        ->get("/auftraege/{$project->public_id}/angebote")
        ->assertOk()
        ->viewData('page')['props'];

    $items = collect($props['quotes'][0]['items']);

    expect($props['quotes'][0]['total'])->toBe(440000)
        ->and($items->firstWhere('title', 'Hochzeitsreportage')['unitPrice'])->toBe(440000)
        ->and($items->firstWhere('title', 'Hochzeitsreportage')['isIncluded'])->toBeFalse()
        ->and($items->firstWhere('title', 'USB-Box in Holz')['unitPrice'])->toBeNull()
        ->and($items->firstWhere('title', 'USB-Box in Holz')['isIncluded'])->toBeTrue()
        ->and($items->firstWhere('title', 'USB-Box in Holz')['lineTotal'])->toBe(0);
});

it('distinguishes a zero-priced line from an included line in the data sent to the screen', function () {
    $project = Project::factory()->create();
    $quote = Quote::factory()->for($project)->create();
    QuoteItem::factory()->for($quote)->create(['title' => 'Kostenlose Zugabe', 'unit_price' => 0]);
    QuoteItem::factory()->for($quote)->included()->create(['title' => 'Enthalten']);

    $props = $this->actingAs(quoteOwner(), 'staff')
        ->get("/auftraege/{$project->public_id}/angebote")
        ->assertOk()
        ->viewData('page')['props'];

    $items = collect($props['quotes'][0]['items']);

    expect($items->firstWhere('title', 'Kostenlose Zugabe'))
        ->toMatchArray(['unitPrice' => 0, 'isIncluded' => false])
        ->and($items->firstWhere('title', 'Enthalten'))
        ->toMatchArray(['unitPrice' => null, 'isIncluded' => true]);
});
