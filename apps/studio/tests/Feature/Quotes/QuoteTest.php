<?php

use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Quotes\Actions\ReviseQuote;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Events\QuoteRevised;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Notifications\QuoteSent;
use App\Quotes\Support\DiscountCalculator;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

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

it('seeds the slider and the payment stages along with the items', function () {
    $package = Package::factory()->create([
        'hourly_rate' => 25000,
        'min_hours' => 4,
        'max_hours' => 10,
        'images_per_hour' => 40,
        'hours' => 6,
    ]);
    $package->paymentStages()->create(['percent' => 30, 'label' => 'Anzahlung', 'minimum' => 50000, 'sort_order' => 0]);
    $package->paymentStages()->create(['percent' => 70, 'label' => 'Restzahlung', 'sort_order' => 1]);
    $quote = Quote::factory()->create();

    app(SeedQuoteFromPackage::class)($quote, $package);

    $quote->refresh();

    expect($quote->hourly_rate)->toBe(25000)
        ->and($quote->min_hours)->toBe(4)
        ->and($quote->max_hours)->toBe(10)
        ->and($quote->images_per_hour)->toBe(40)
        ->and($quote->hours)->toBe(6)
        ->and($quote->paymentStages->pluck('label')->all())->toBe(['Anzahlung', 'Restzahlung'])
        ->and($quote->paymentStages->first()->minimum)->toBe(50000);
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

// --- T-05: quote validity falls back to the tenant's document default ---

describe('T-05: quote validity default', function () {
    beforeEach(function () {
        // Redis is shared infrastructure across the whole test run (see
        // StudioSettingsResolver's own docblock) — flushed here for the same
        // reason the Q-04 describe block above does.
        Cache::store('redis')->flush();
    });

    it('takes its validity from the tenant setting when created without a package and no explicit valid_until', function () {
        app(StudioSettingsResolver::class)->set('quote_validity_days', 21);
        $project = Project::factory()->create();

        $this->actingAs(quoteOwner(), 'staff')
            ->post("/auftraege/{$project->public_id}/angebote", [])
            ->assertRedirect();

        $quote = $project->quotes()->sole();

        expect($quote->valid_until->format('Y-m-d'))->toBe(now()->addDays(21)->format('Y-m-d'));
    });

    it('leaves an explicit valid_until alone rather than overriding it with the default', function () {
        app(StudioSettingsResolver::class)->set('quote_validity_days', 21);
        $project = Project::factory()->create();

        $this->actingAs(quoteOwner(), 'staff')
            ->post("/auftraege/{$project->public_id}/angebote", ['valid_until' => '2026-12-31'])
            ->assertRedirect();

        expect($project->quotes()->sole()->valid_until->format('Y-m-d'))->toBe('2026-12-31');
    });

    it('moves no existing quote\'s valid_until when the default changes afterwards', function () {
        app(StudioSettingsResolver::class)->set('quote_validity_days', 14);
        $project = Project::factory()->create();

        $this->actingAs(quoteOwner(), 'staff')
            ->post("/auftraege/{$project->public_id}/angebote", []);

        $quote = $project->quotes()->sole();
        $originalValidUntil = $quote->valid_until->format('Y-m-d');

        app(StudioSettingsResolver::class)->set('quote_validity_days', 60);

        expect($quote->fresh()->valid_until->format('Y-m-d'))->toBe($originalValidUntil);
    });
});

it('replaces a draft quote\'s items wholesale on update, recalculates the total, and logs it', function () {
    $user = quoteOwner();
    $quote = Quote::factory()->create();
    $original = QuoteItem::factory()->for($quote)->create(['title' => 'Alte Position']);

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'valid_until' => '2027-01-15',
        'pricing_mode' => 'classic',
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

// --- Q-03: line discount resolved to an amount at entry -----------------

it('resolves a percentage line discount to an amount the moment the line is saved', function () {
    // 10.50% (1050 basis points) of 10.101,00 € does not divide evenly into
    // whole cents: 1.010.100 × 1050 = 1.060.605.000, /10000 = 106.060,5 —
    // round-half-up (the one rounding this line performs) takes it to 106061.
    $item = QuoteItem::factory()->for(Quote::factory())->create([
        'quantity' => 1,
        'unit_price' => 1010100,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 1050,
    ]);

    expect($item->fresh()->discount_amount)->toBe(106061)
        ->and($item->fresh()->discountedLineTotal())->toBe(1010100 - 106061);
});

it('resolves an amount line discount straight through, capped at the line total', function () {
    $item = QuoteItem::factory()->for(Quote::factory())->create([
        'quantity' => 1,
        'unit_price' => 5000,
        'discount_type' => DiscountType::Amount,
        'discount_value' => 20000,
    ]);

    expect($item->fresh()->discount_amount)->toBe(5000)
        ->and($item->fresh()->discountedLineTotal())->toBe(0);
});

it('recomputes discount_amount on every save, never leaving it stale', function () {
    $item = QuoteItem::factory()->for(Quote::factory())->create([
        'quantity' => 1,
        'unit_price' => 10000,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 1000,
    ]);
    expect($item->fresh()->discount_amount)->toBe(1000);

    $item->update(['unit_price' => 20000]);

    expect($item->fresh()->discount_amount)->toBe(2000);
});

it('keeps an included line at zero discount and zero net regardless of what discount fields carry', function () {
    $item = QuoteItem::factory()->for(Quote::factory())->included()->create([
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 5000,
    ]);

    expect($item->fresh()->discount_amount)->toBe(0)
        ->and($item->fresh()->discountedLineTotal())->toBe(0);
});

// --- Q-03: quote-level discount, applied to the already-line-discounted sum ---

it('applies the quote-level discount to the sum of the already-line-discounted lines, excluding included lines from both bases', function () {
    $quote = Quote::factory()->create([
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 500, // 5%
    ]);
    // Line 1: 1.000,00 € less a 10% line discount = 900,00 €.
    QuoteItem::factory()->for($quote)->create([
        'quantity' => 1,
        'unit_price' => 100000,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 1000,
    ]);
    // Line 2: 500,00 € flat, no line discount.
    QuoteItem::factory()->for($quote)->create(['quantity' => 1, 'unit_price' => 50000]);
    // Included line: contributes nothing to either base.
    QuoteItem::factory()->for($quote)->included()->create();

    $quote->recalculateTotal();
    $quote->refresh();

    // Subtotal (already-line-discounted, included line excluded): 900 + 500 = 1.400,00 €.
    // Quote discount: 5% of 140000 = 7000. Net: 140000 - 7000 = 133000.
    expect($quote->total)->toBe(150000)
        ->and($quote->discount_amount)->toBe(7000)
        ->and($quote->net)->toBe(133000);
});

it('caps a quote-level discount at the subtotal so net never goes negative through the app path', function () {
    $quote = Quote::factory()->create([
        'discount_type' => DiscountType::Amount,
        'discount_value' => 999999999,
    ]);
    QuoteItem::factory()->for($quote)->create(['quantity' => 1, 'unit_price' => 10000]);

    $quote->recalculateTotal();

    expect($quote->fresh()->discount_amount)->toBe(10000)
        ->and($quote->fresh()->net)->toBe(0);
});

it('produces a total matching a hand calculation to the cent: a quote-level percentage discount over an already line-discounted sum', function () {
    // Line: 101,01 € × 1, 10.50% line discount.
    //   raw = 10101 × 1050 = 10.606.050 → round-half-up /10000 = 1061 → line net = 9040.
    // Quote: 3.33% (333 bps) on the subtotal (9040, a single line's worth here).
    //   raw = 9040 × 333 = 3.010.320 → round-half-up /10000 = 301 → net = 9040 - 301 = 8739.
    $quote = Quote::factory()->create([
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 333,
    ]);
    QuoteItem::factory()->for($quote)->create([
        'quantity' => 1,
        'unit_price' => 10101,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 1050,
    ]);

    $quote->recalculateTotal();

    expect($quote->fresh()->net)->toBe(8739);
});

// --- Q-03: net >= 0 is a database constraint, not only an app one -------

it('lets the database itself reject a negative net, bypassing app logic entirely via a raw model save', function () {
    $quote = Quote::factory()->create();

    expect(fn () => $quote->forceFill(['net' => -1])->saveQuietly())
        ->toThrow(QueryException::class);
});

// --- Q-03: property test — total always equals an independent recomputation ---

it('always equals an independent recomputation from the raw inputs, across many random combinations of discounts', function () {
    // No property-testing plugin ships with this repo's Pest (composer.json
    // has no faker/eris-style package for it) — a seeded, many-iteration
    // randomized sweep is the parameterized equivalent without adding one.
    mt_srand(20260809);

    for ($run = 0; $run < 200; $run++) {
        $lineCount = mt_rand(1, 4);
        $lines = [];
        $expectedSubtotal = 0;

        for ($i = 0; $i < $lineCount; $i++) {
            $included = mt_rand(0, 4) === 0; // occasionally include one
            $quantity = mt_rand(1, 5);
            $unitPrice = mt_rand(0, 500000);
            $discountType = match (mt_rand(0, 2)) {
                0 => null,
                1 => DiscountType::Percentage,
                2 => DiscountType::Amount,
            };
            $discountValue = match (true) {
                $discountType === null => null,
                $discountType === DiscountType::Percentage => mt_rand(0, 10000),
                default => mt_rand(0, 600000),
            };

            $lineTotal = $included ? 0 : $quantity * $unitPrice;

            // Independent recomputation of this line's own discount amount,
            // expressed fresh here rather than by calling
            // App\Quotes\Support\DiscountCalculator.
            $lineDiscount = 0;
            if (! $included && $discountType !== null && $discountValue > 0 && $lineTotal > 0) {
                $lineDiscount = $discountType === DiscountType::Percentage
                    ? intdiv($lineTotal * $discountValue + 5000, 10000)
                    : $discountValue;
                $lineDiscount = max(0, min($lineDiscount, $lineTotal));
            }

            $lines[] = compact('included', 'quantity', 'unitPrice', 'discountType', 'discountValue');
            $expectedSubtotal += $lineTotal - $lineDiscount;
        }

        $quoteDiscountType = match (mt_rand(0, 2)) {
            0 => null,
            1 => DiscountType::Percentage,
            2 => DiscountType::Amount,
        };
        $quoteDiscountValue = match (true) {
            $quoteDiscountType === null => null,
            $quoteDiscountType === DiscountType::Percentage => mt_rand(0, 10000),
            default => mt_rand(0, 2000000),
        };

        $expectedQuoteDiscount = 0;
        if ($quoteDiscountType !== null && $quoteDiscountValue > 0 && $expectedSubtotal > 0) {
            $expectedQuoteDiscount = $quoteDiscountType === DiscountType::Percentage
                ? intdiv($expectedSubtotal * $quoteDiscountValue + 5000, 10000)
                : $quoteDiscountValue;
            $expectedQuoteDiscount = max(0, min($expectedQuoteDiscount, $expectedSubtotal));
        }
        $expectedNet = $expectedSubtotal - $expectedQuoteDiscount;

        $quote = Quote::factory()->create([
            'discount_type' => $quoteDiscountType,
            'discount_value' => $quoteDiscountValue,
        ]);
        foreach ($lines as $line) {
            QuoteItem::factory()->for($quote)->create([
                'quantity' => $line['quantity'],
                'unit_price' => $line['included'] ? null : $line['unitPrice'],
                'discount_type' => $line['discountType'],
                'discount_value' => $line['discountValue'],
            ]);
        }

        $quote->recalculateTotal();

        expect($quote->fresh()->net)->toBe($expectedNet)
            ->and($quote->fresh()->net)->toBeGreaterThanOrEqual(0);
    }
});

// --- Q-04: VAT snapshot --------------------------------------------------

describe('Q-04: VAT snapshot', function () {
    beforeEach(function () {
        // Redis is shared infrastructure across the whole test run (see
        // StudioSettingsResolver's own docblock and StudioSettingsResolverTest)
        // — flushed here for the same reason, so a value cached by an earlier
        // test file never leaks into what these tests read.
        Cache::store('redis')->flush();
    });

    it('leaves a draft quote\'s VAT columns null until something snapshots them', function () {
        $quote = Quote::factory()->create();

        expect($quote->vat_mode)->toBeNull()
            ->and($quote->vat_rate)->toBeNull()
            ->and($quote->vat_note)->toBeNull()
            ->and($quote->vat_amount)->toBeNull();
    });

    it('snapshots the standard rate, computing vat_amount off the already-rounded net via DiscountCalculator', function () {
        app(StudioSettingsResolver::class)->set('vat_mode', 'standard');
        app(StudioSettingsResolver::class)->set('vat_rate', 1900); // 19.00%

        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->create(['quantity' => 1, 'unit_price' => 453782]);
        $quote->recalculateTotal();
        $quote->refresh();
        expect($quote->net)->toBe(453782);

        $quote->snapshotVat(app(StudioSettingsResolver::class));
        $quote->refresh();

        expect($quote->vat_mode)->toBe('standard')
            ->and($quote->vat_rate)->toBe(1900)
            ->and($quote->vat_note)->toBeNull()
            ->and($quote->vat_amount)->toBe(DiscountCalculator::resolve(453782, DiscountType::Percentage, 1900));
    });

    it('produces a vat_amount matching a hand calculation, rounded half up off the net', function () {
        // 4.537,82 € net at 19.00%: 453782 × 1900 = 862.186.580,
        // + half the basis-point divisor (5.000), /10000 rounded down
        // (DiscountCalculator's round-half-up) = 86.219 (862,19 €).
        app(StudioSettingsResolver::class)->set('vat_mode', 'standard');
        app(StudioSettingsResolver::class)->set('vat_rate', 1900);

        $quote = Quote::factory()->create(['net' => 453782]);
        $quote->snapshotVat(app(StudioSettingsResolver::class));

        expect($quote->fresh()->vat_amount)->toBe(86219);
    });

    it('snapshots small_business as zero VAT charged, printing the note instead of a rate', function () {
        app(StudioSettingsResolver::class)->set('vat_mode', 'small_business');
        app(StudioSettingsResolver::class)->set('vat_note', 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');

        $quote = Quote::factory()->create(['net' => 100000]);
        $quote->snapshotVat(app(StudioSettingsResolver::class));
        $quote->refresh();

        expect($quote->vat_mode)->toBe('small_business')
            ->and($quote->vat_rate)->toBe(0)
            ->and($quote->vat_amount)->toBe(0)
            ->and($quote->vat_note)->toBe('Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');
    });

    it('ignores studio_settings.vat_rate entirely under small_business, matching effectiveVatRate()', function () {
        // T-03's own rule: vat_rate is never zeroed by a mode switch, so a
        // studio holds e.g. 19% on file while small_business is active. The
        // snapshot must still charge nothing.
        app(StudioSettingsResolver::class)->set('vat_rate', 1900);
        app(StudioSettingsResolver::class)->set('vat_mode', 'small_business');

        $quote = Quote::factory()->create(['net' => 100000]);
        $quote->snapshotVat(app(StudioSettingsResolver::class));

        expect($quote->fresh()->vat_rate)->toBe(0)
            ->and($quote->fresh()->vat_amount)->toBe(0);
    });

    it('leaves an already-snapshotted quote entirely unaffected when the tenant later changes its VAT mode', function () {
        app(StudioSettingsResolver::class)->set('vat_mode', 'standard');
        app(StudioSettingsResolver::class)->set('vat_rate', 1900);

        $quote = Quote::factory()->create(['net' => 500000]);
        $quote->snapshotVat(app(StudioSettingsResolver::class));

        $before = $quote->fresh()->only(['vat_mode', 'vat_rate', 'vat_note', 'vat_amount']);

        // The studio switches to small_business after the quote already
        // carries its own snapshot — Q-04's whole point, and the reason
        // nothing here re-reads StudioSettingsResolver.
        app(StudioSettingsResolver::class)->set('vat_mode', 'small_business');

        expect($quote->fresh()->only(['vat_mode', 'vat_rate', 'vat_note', 'vat_amount']))->toBe($before);
    });

    it('sends a quote\'s own VAT snapshot columns to the screen once populated, and a live preview for a draft', function () {
        $project = Project::factory()->create();
        $draft = Quote::factory()->for($project)->create(['net' => 50000]);

        $sent = Quote::factory()->for($project)->create(['net' => 100000]);
        app(StudioSettingsResolver::class)->set('vat_mode', 'standard');
        app(StudioSettingsResolver::class)->set('vat_rate', 1900);
        $sent->snapshotVat(app(StudioSettingsResolver::class));

        // The tenant's rate changes after the send — the sent quote must not
        // see it, the still-unsent draft must, since nothing of its is
        // frozen yet.
        app(StudioSettingsResolver::class)->set('vat_rate', 700);

        $props = $this->actingAs(quoteOwner(), 'staff')
            ->get("/auftraege/{$project->public_id}/angebote")
            ->assertOk()
            ->viewData('page')['props'];

        $quotes = collect($props['quotes'])->keyBy('id');

        expect($quotes[$draft->public_id]['vatMode'])->toBe('standard')
            ->and($quotes[$draft->public_id]['vatRate'])->toBe(700)
            ->and($quotes[$draft->public_id]['vatAmount'])->toBe(Quote::computeVat(50000, app(StudioSettingsResolver::class))['vat_amount'])
            ->and($quotes[$sent->public_id]['vatMode'])->toBe('standard')
            ->and($quotes[$sent->public_id]['vatRate'])->toBe(1900)
            ->and($quotes[$sent->public_id]['vatAmount'])->toBe($sent->fresh()->vat_amount);
    });
});

// --- Q-06: sending a quote -------------------------------------------------

describe('Q-06: sending a quote', function () {
    beforeEach(function () {
        // Same reasoning as the Q-04 describe block above: Redis is shared
        // across the whole test run, and SendQuote calls snapshotVat().
        Cache::store('redis')->flush();
    });

    it('snapshots VAT, sets sent_at, and freezes the already-allocated number and computed amounts', function () {
        $quote = Quote::factory()->create(['number' => 'AN-2026-1']);
        QuoteItem::factory()->for($quote)->create(['quantity' => 1, 'unit_price' => 453782]);
        $quote->recalculateTotal();

        $sent = app(SendQuote::class)($quote->fresh());

        expect($sent->status)->toBe(QuoteStatus::Sent)
            ->and($sent->sent_at)->not->toBeNull()
            ->and($sent->number)->toBe('AN-2026-1')
            ->and($sent->vat_mode)->toBe('standard')
            ->and($sent->vat_rate)->toBe(1900)
            ->and($sent->net)->toBe(453782);
    });

    it('leaves each quote\'s own number untouched, independent across separate sends', function () {
        $first = Quote::factory()->create(['number' => 'AN-2026-1']);
        $second = Quote::factory()->create(['number' => 'AN-2026-2']);

        app(SendQuote::class)($first);
        app(SendQuote::class)($second);

        expect($first->fresh()->number)->toBe('AN-2026-1')
            ->and($second->fresh()->number)->toBe('AN-2026-2');
    });

    it('refuses to send a quote that is already sent or superseded', function (QuoteStatus $status) {
        $quote = Quote::factory()->create(['status' => $status]);

        expect(fn () => app(SendQuote::class)($quote))->toThrow(DomainException::class);
    })->with([QuoteStatus::Sent, QuoteStatus::Superseded]);

    it('logs the send with the allocated number', function () {
        $user = quoteOwner();
        $quote = Quote::factory()->create();

        app(SendQuote::class)($quote, $user);

        $row = AuditLog::where('event', 'quote.sent')->sole();

        expect($row->actor_id)->toBe($user->id)
            ->and($row->subject)->toBe($quote->public_id)
            ->and($row->context['number'])->toBe($quote->fresh()->number);
    });

    it('mails the project\'s primary contact synchronously', function () {
        Notification::fake();
        $project = Project::factory()->create();
        $contact = Contact::factory()->create(['email' => 'lena@example.test']);
        $project->attachContact($contact, ProjectContactRole::Primary);
        $quote = Quote::factory()->for($project)->create();

        app(SendQuote::class)($quote);

        // T-11: through NotificationService, addressed to the Contact
        // itself — see QuoteSent's own docblock — so client preferences and
        // quiet hours have an identity to key on, not an on-demand route.
        Notification::assertSentTo($contact, QuoteSent::class);
    });

    it('links the mail to the portal quote screen only while the project is portal_visible', function () {
        $contact = Contact::factory()->create(['email' => 'lena@example.test']);

        $visible = Project::factory()->create(['portal_visible' => true]);
        $visible->attachContact($contact, ProjectContactRole::Primary);
        $hidden = Project::factory()->create(['portal_visible' => false]);
        $hidden->attachContact($contact, ProjectContactRole::Primary);

        $withButton = (new QuoteSent(Quote::factory()->for($visible)->create()))->toMail($contact);
        $withoutButton = (new QuoteSent(Quote::factory()->for($hidden)->create()))->toMail($contact);

        // A button into the portal's own 404 would be worse than no button:
        // the route is bound on visibility, not just on the contact.
        expect($withButton->actionUrl)->toContain("/projekte/{$visible->public_id}/angebot")
            ->and($withoutButton->actionUrl)->toBeNull();
    });

    it('composes both portal links against the portal host, not the admin surface it is sent from', function () {
        // PORTAL_URL only diverges from APP_URL once the surfaces get their
        // own hosts, which is the single setup where url() would be wrong —
        // and the one config/portal.php's fallback hides everywhere else.
        config(['portal.url' => 'https://kunden.example.test']);
        URL::forceRootUrl('https://studio.example.test');

        $contact = Contact::factory()->create(['email' => 'lena@example.test']);
        $project = Project::factory()->create(['portal_visible' => true]);
        $project->attachContact($contact, ProjectContactRole::Primary);

        $mail = (new QuoteSent(Quote::factory()->for($project)->create()))->toMail($contact);
        $body = (string) $mail->render();

        expect($mail->actionUrl)->toBe("https://kunden.example.test/projekte/{$project->public_id}/angebot")
            ->and($body)->toContain("https://kunden.example.test/abmelden/{$contact->public_id}")
            ->and($body)->not->toContain('studio.example.test/projekte')
            ->and($body)->not->toContain('studio.example.test/abmelden');
    });

    it('sends no mail when the project has no contactable primary contact', function () {
        Notification::fake();
        $quote = Quote::factory()->create();

        app(SendQuote::class)($quote);

        Notification::assertNothingSent();
    });

    it('is reachable through the controller, authorized the same as every other quote mutation', function () {
        $user = quoteOwner();
        $project = Project::factory()->create();
        $quote = Quote::factory()->for($project)->create();
        QuoteItem::factory()->for($quote)->create();

        $this->actingAs($user, 'staff')
            ->post("/angebote/{$quote->public_id}/versenden")
            ->assertRedirect();

        expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('opens the compose screen for a draft quote, carrying the recipient it would mail', function () {
        $contact = Contact::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@example.test']);
        $project = Project::factory()->create();
        $project->attachContact($contact, ProjectContactRole::Primary);
        $quote = Quote::factory()->for($project)->create();
        QuoteItem::factory()->for($quote)->create();

        $this->actingAs(quoteOwner(), 'staff')
            ->get("/angebote/{$quote->public_id}/senden")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotes/Send')
                ->where('recipientName', 'Lena Brandt')
                ->where('recipientEmail', 'lena@example.test'));
    });

    it('refuses the compose screen once a quote is no longer a draft', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent]);

        $this->actingAs(quoteOwner(), 'staff')
            ->get("/angebote/{$quote->public_id}/senden")
            ->assertNotFound();
    });

    it('carries the compose screen\'s subject and message into the mail', function () {
        Notification::fake();

        $contact = Contact::factory()->create(['email' => 'lena@example.test']);
        $project = Project::factory()->create();
        $project->attachContact($contact, ProjectContactRole::Primary);
        $quote = Quote::factory()->for($project)->create();
        QuoteItem::factory()->for($quote)->create();

        $this->actingAs(quoteOwner(), 'staff')->post("/angebote/{$quote->public_id}/versenden", [
            'subject' => 'Euer Angebot ist da',
            'message' => "Hallo Lena,\n\nvielen Dank für euer Vertrauen.",
        ]);

        Notification::assertSentTo($contact, function (QuoteSent $notification) use ($contact) {
            $mail = $notification->toMail($contact);

            return $mail->subject === 'Euer Angebot ist da'
                && str_contains((string) $mail->render(), 'vielen Dank für euer Vertrauen.');
        });
    });
});

// --- Q-06: a sent quote is immutable at the model level --------------------

describe('Q-06: a sent quote is immutable at the model level', function () {
    it('refuses a raw Eloquent write to a snapshotted column on a sent quote', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent, 'number' => 'AN-2026-1', 'sent_at' => now()]);

        expect(fn () => $quote->update(['net' => 1]))->toThrow(RuntimeException::class);
        expect(fn () => $quote->update(['valid_until' => '2099-01-01']))->toThrow(RuntimeException::class);
        expect(fn () => $quote->update(['vat_rate' => 0]))->toThrow(RuntimeException::class);
        expect(fn () => $quote->update(['discount_value' => 500]))->toThrow(RuntimeException::class);
    });

    it('leaves the row unchanged in the database after a refused write', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent, 'net' => 100000]);

        try {
            $quote->update(['net' => 1]);
        } catch (RuntimeException) {
            // expected — asserted below by re-reading the row
        }

        expect($quote->fresh()->net)->toBe(100000);
    });

    it('allows the one write ReviseQuote performs — status alone, sent to superseded', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent]);

        $quote->update(['status' => QuoteStatus::Superseded]);

        expect($quote->fresh()->status)->toBe(QuoteStatus::Superseded);
    });

    it('refuses a status change bundled with any other column, even on a sent quote', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent, 'net' => 100000]);

        expect(fn () => $quote->update(['status' => QuoteStatus::Superseded, 'net' => 1]))
            ->toThrow(RuntimeException::class);
    });

    it('refuses every write on a superseded quote, including status', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Superseded]);

        expect(fn () => $quote->update(['status' => QuoteStatus::Sent]))->toThrow(RuntimeException::class);
        expect(fn () => $quote->update(['net' => 1]))->toThrow(RuntimeException::class);
    });

    it('still lets a draft quote be edited normally, unaffected by the guard', function () {
        $quote = Quote::factory()->create();

        $quote->update(['valid_until' => '2027-06-01']);

        expect($quote->fresh()->valid_until->format('Y-m-d'))->toBe('2027-06-01');
    });
});

// --- Q-06: revising a sent quote --------------------------------------------

describe('Q-06: revising a sent quote', function () {
    it('creates a new row carrying the predecessor\'s id, copying its items, discount and validity', function () {
        $project = Project::factory()->create();
        $predecessor = Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Sent,
            'valid_until' => '2026-12-31',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 500,
        ]);
        QuoteItem::factory()->for($predecessor)->create(['title' => 'Hochzeitsreportage', 'quantity' => 1, 'unit_price' => 440000]);

        $revision = app(ReviseQuote::class)($predecessor);

        expect($revision->id)->not->toBe($predecessor->id)
            ->and($revision->predecessor_id)->toBe($predecessor->id)
            ->and($revision->status)->toBe(QuoteStatus::Draft)
            ->and($revision->valid_until->format('Y-m-d'))->toBe('2026-12-31')
            ->and($revision->discount_type)->toBe(DiscountType::Percentage)
            ->and($revision->discount_value)->toBe(500)
            ->and($revision->items)->toHaveCount(1)
            ->and($revision->items->first()->title)->toBe('Hochzeitsreportage')
            ->and($revision->total)->toBe(440000);
    });

    it('supersedes the predecessor in the same transaction as the revision insert', function () {
        $predecessor = Quote::factory()->create(['status' => QuoteStatus::Sent]);

        $revision = app(ReviseQuote::class)($predecessor);

        expect($predecessor->fresh()->status)->toBe(QuoteStatus::Superseded)
            ->and($revision->fresh()->predecessor->public_id)->toBe($predecessor->public_id);
    });

    it('rolls back both the revision insert and the supersede if anything fails mid-transaction', function () {
        // QuoteRevised fires last, inside ReviseQuote's own transaction —
        // after the revision row and the predecessor's status update have
        // both already been written on this connection — the only cheap
        // place to prove the rollback is real, the same trick
        // CaptureInquiryTest uses for LeadCaptured.
        Event::listen(QuoteRevised::class, fn () => throw new RuntimeException('boom'));

        $predecessor = Quote::factory()->create(['status' => QuoteStatus::Sent]);

        expect(fn () => app(ReviseQuote::class)($predecessor))->toThrow(RuntimeException::class);

        expect(Quote::count())->toBe(1)
            ->and($predecessor->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('refuses to revise a quote that is not sent', function (QuoteStatus $status) {
        $quote = Quote::factory()->create(['status' => $status]);

        expect(fn () => app(ReviseQuote::class)($quote))->toThrow(DomainException::class);
    })->with([QuoteStatus::Draft, QuoteStatus::Superseded]);

    it('leaves the predecessor reachable and reading as superseded on the project\'s quotes screen', function () {
        $project = Project::factory()->create();
        $predecessor = Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'number' => 'AN-2026-37']);
        QuoteItem::factory()->for($predecessor)->create();
        $revision = app(ReviseQuote::class)($predecessor);

        $props = $this->actingAs(quoteOwner(), 'staff')
            ->get("/auftraege/{$project->public_id}/angebote")
            ->assertOk()
            ->viewData('page')['props'];

        $quotes = collect($props['quotes'])->keyBy('id');

        expect($quotes)->toHaveCount(2)
            ->and($quotes[$predecessor->public_id]['status'])->toBe('superseded')
            ->and($quotes[$predecessor->public_id]['statusLabel'])->toBe('Ersetzt')
            ->and($quotes[$revision->fresh()->public_id]['predecessorId'])->toBe($predecessor->public_id);
    });
});

// --- Q-07: a sent quote also allows the accept transition, then refuses everything ---

describe('Q-07: a sent quote allows the accept transition, then refuses everything', function () {
    it('allows the bundled write App\Quotes\Actions\AcceptQuote performs — status plus the three acceptance columns, sent to accepted', function () {
        $contact = Contact::factory()->create();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent]);

        $quote->update([
            'status' => QuoteStatus::Accepted,
            'accepted_at' => now(),
            'accepted_ip' => '127.0.0.1',
            'accepted_by_contact_id' => $contact->id,
        ]);

        expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
            ->and($quote->fresh()->accepted_by_contact_id)->toBe($contact->id);
    });

    it('refuses every write on an accepted quote, including status', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Accepted, 'accepted_at' => now()]);

        expect(fn () => $quote->update(['status' => QuoteStatus::Sent]))->toThrow(RuntimeException::class);
        expect(fn () => $quote->update(['net' => 1]))->toThrow(RuntimeException::class);
    });

    it('refuses the accept transition bundled with any other column, even on a sent quote', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent, 'net' => 100000]);

        expect(fn () => $quote->update(['status' => QuoteStatus::Accepted, 'accepted_at' => now(), 'net' => 1]))
            ->toThrow(RuntimeException::class);
    });
});

// --- Q-07: Project::latestQuote() — the one the portal shows ---------------

describe('Q-07: Project::latestQuote()', function () {
    beforeEach(function () {
        // Same reasoning as the Q-04/Q-06 describe blocks above: Redis is
        // shared across the whole test run, and SendQuote calls snapshotVat().
        Cache::store('redis')->flush();
    });

    it('is null for a project that has never sent a quote', function () {
        $project = Project::factory()->create();
        Quote::factory()->for($project)->create(); // draft, never sent

        expect($project->latestQuote)->toBeNull();
    });

    it('still points at the superseded predecessor until its own revision is sent', function () {
        $project = Project::factory()->create();
        $predecessor = Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Sent,
            'number' => 'AN-2026-1',
            'sent_at' => now(),
        ]);
        $revision = app(ReviseQuote::class)($predecessor);

        expect($project->latestQuote->id)->toBe($predecessor->id);

        app(SendQuote::class)($revision);

        expect($project->fresh()->latestQuote->id)->toBe($revision->fresh()->id);
    });
});

// --- Autosave: the audit entry is throttled, not dropped ----------------

it('writes one quote.updated for a burst of autosaves, and one again after the window', function () {
    $user = quoteOwner();
    $quote = Quote::factory()->create();

    $payload = ['pricing_mode' => 'classic', 'items' => [['title' => 'Reportage', 'description' => null, 'quantity' => 1, 'unit_price' => 440000]]];

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", $payload);
    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", $payload);

    expect(AuditLog::where('event', 'quote.updated')->where('subject', $quote->public_id)->count())->toBe(1);

    $this->travel(16)->minutes();
    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", $payload);

    expect(AuditLog::where('event', 'quote.updated')->where('subject', $quote->public_id)->count())->toBe(2);
});

it('does not let one quote\'s autosave suppress another quote\'s entry', function () {
    $user = quoteOwner();
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();

    $payload = ['pricing_mode' => 'classic', 'items' => [['title' => 'Reportage', 'description' => null, 'quantity' => 1, 'unit_price' => 440000]]];

    $this->actingAs($user, 'staff')->patch("/angebote/{$first->public_id}", $payload);
    $this->actingAs($user, 'staff')->patch("/angebote/{$second->public_id}", $payload);

    expect(AuditLog::where('event', 'quote.updated')->count())->toBe(2);
});

// The lead pill creates the quote and fills it, rather than only opening the
// tab — the store() branch that seeds from a package keeps its caller here.
it('creates a quote seeded from a package when the lead pill posts one', function () {
    $project = Project::factory()->create();
    $package = Package::factory()->has(PackageItem::factory()->count(2), 'items')->create();

    $this->actingAs(quoteOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['package' => $package->public_id])
        ->assertRedirect();

    expect($project->quotes()->sole()->items)->toHaveCount(2);
});

// --- Creating: no dialog, so no questions before the first visible line ---

it('creates a blank draft straight from the button, taking validity from the studio default', function () {
    $project = Project::factory()->create();

    $this->actingAs(quoteOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote")
        ->assertRedirect();

    $quote = $project->quotes()->sole();

    expect($quote->items)->toHaveCount(0)
        ->and($quote->valid_until)->not->toBeNull();
});

// --- Merken: the tick is derived, and it writes the catalog -------------

it('remembers a flagged line, and remembers it only once across quotes', function () {
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();
    $line = ['title' => 'Zweitfotograf', 'description' => 'Zweite Kamera', 'quantity' => 1, 'unit_price' => 49000, 'remember' => true];

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$first->public_id}", ['pricing_mode' => 'classic', 'items' => [$line]]);
    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$second->public_id}", ['pricing_mode' => 'classic', 'items' => [$line]]);

    $item = CatalogItem::sole();

    expect($item->title)->toBe('Zweitfotograf')
        ->and($item->description)->toBe('Zweite Kamera')
        ->and($item->unit_price)->toBe(49000);
});

it('updates the remembered price and description, and never the quantity', function () {
    $quote = Quote::factory()->create();
    CatalogItem::create(['title' => 'Zweitfotograf', 'description' => null, 'unit_price' => 49000]);

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'items' => [['title' => 'Zweitfotograf', 'description' => 'Ganztags', 'quantity' => 3, 'unit_price' => 52000, 'remember' => true]],
    ]);

    $item = CatalogItem::sole();

    expect($item->unit_price)->toBe(52000)
        ->and($item->description)->toBe('Ganztags')
        ->and($item->getAttributes())->not->toHaveKey('quantity');
});

it('forgets a line whose flag is taken away', function () {
    $quote = Quote::factory()->create();
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'items' => [['title' => 'Zweitfotograf', 'description' => null, 'quantity' => 1, 'unit_price' => 49000, 'remember' => false]],
    ]);

    expect(CatalogItem::count())->toBe(0);
});

it('leaves the library alone when no line carries the flag at all', function () {
    $quote = Quote::factory()->create();
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'items' => [['title' => 'Reportage', 'description' => null, 'quantity' => 1, 'unit_price' => 189000]],
    ]);

    expect(CatalogItem::count())->toBe(1);
});

// The gate exists for API tokens (P6/I-01), not for roles — every role holds
// ManagePackages today, which is exactly what PackagePolicy explains.
it('refuses to write the library for an actor without the catalog capability', function () {
    $role = collect(StaffRole::cases())->first(fn (StaffRole $role) => ! $role->allows(Capability::ManagePackages));

    if ($role === null) {
        $this->markTestSkipped('Jede Rolle hält ManagePackages — die Trennung existiert nur für API-Token (P6/I-01).');
    }

    $quote = Quote::factory()->create();

    $this->actingAs(staffMember($role), 'staff')->patch("/angebote/{$quote->public_id}", [
        'items' => [['title' => 'Zweitfotograf', 'description' => null, 'quantity' => 1, 'unit_price' => 49000, 'remember' => true]],
    ])->assertForbidden();

    expect(CatalogItem::count())->toBe(0);
});

it('sends active packages with their items, the library and the catalog permission to the quotes screen', function () {
    $quote = Quote::factory()->create();
    Package::factory()->has(PackageItem::factory()->count(2), 'items')->create(['name' => 'Hochzeit Basis']);
    Package::factory()->inactive()->create(['name' => 'Alt']);
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    $props = $this->actingAs(quoteOwner(), 'staff')
        ->get("/angebote/{$quote->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['packages'])->pluck('name')->all())->toBe(['Hochzeit Basis'])
        ->and($props['packages'][0]['items'])->toHaveCount(2)
        ->and($props['catalogItems'][0]['title'])->toBe('Zweitfotograf')
        ->and($props['canManageCatalog'])->toBeTrue();
});

// --- QuoteActions.vue's split button: remembered per user -----------------

describe('remembering the last quote action a user took', function () {
    it('writes one of the three known actions onto the acting user', function () {
        $user = quoteOwner();

        $this->actingAs($user, 'staff')
            ->post('/angebote/letzte-aktion', ['action' => 'copy_link'])
            ->assertRedirect();

        expect($user->fresh()->last_quote_action)->toBe('copy_link');
    });

    it('refuses anything outside the three known actions', function () {
        $this->actingAs(quoteOwner(), 'staff')
            ->post('/angebote/letzte-aktion', ['action' => 'delete_everything'])
            ->assertSessionHasErrors('action');
    });

    it('sends it back down as the quote screen loads, per user rather than per quote', function () {
        $project = Project::factory()->create();
        $user = quoteOwner();
        $user->update(['last_quote_action' => 'send_by_mail']);

        $props = $this->actingAs($user, 'staff')
            ->get("/auftraege/{$project->public_id}/angebote")
            ->assertOk()
            ->viewData('page')['props'];

        expect($props['auth']['user']['lastQuoteAction'])->toBe('send_by_mail');
    });
});

// --- QuoteActions.vue's 3-dot menu: duplicate and delete -------------------

describe('duplicating a quote', function () {
    it('opens a new draft on the same project, carrying positions, terms and payment stages, none of the source\'s history', function () {
        $project = Project::factory()->create();
        $source = Quote::factory()->for($project)->create([
            'status' => QuoteStatus::Sent,
            'number' => 'AN-2026-1',
            'sent_at' => now(),
            'sent_channel' => 'mail',
            'intro' => 'Liebe Grüße',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1000,
            'vat_mode' => 'standard',
            'vat_rate' => 1900,
            'vat_amount' => 5000,
        ]);
        QuoteItem::factory()->for($source)->create(['title' => 'Reportage', 'unit_price' => 200000]);
        $source->paymentStages()->create(['percent' => 100, 'label' => 'Bei der Zusage', 'sort_order' => 0]);

        $this->actingAs(quoteOwner(), 'staff')
            ->post("/angebote/{$source->public_id}/duplizieren")
            ->assertRedirect();

        $copy = Quote::where('id', '!=', $source->id)->firstOrFail();

        expect($copy->project_id)->toBe($project->id)
            ->and($copy->status)->toBe(QuoteStatus::Draft)
            ->and($copy->number)->not->toBeNull()
            ->and($copy->sent_at)->toBeNull()
            ->and($copy->intro)->toBe('Liebe Grüße')
            ->and($copy->discount_value)->toBe(1000)
            ->and($copy->vat_mode)->toBeNull()
            ->and($copy->items->pluck('title')->all())->toBe(['Reportage'])
            ->and($copy->paymentStages->pluck('label')->all())->toBe(['Bei der Zusage'])
            ->and($copy->total)->toBe($copy->items->first()->unit_price);
    });

    it('leaves the source quote untouched', function () {
        $project = Project::factory()->create();
        $source = Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted]);
        QuoteItem::factory()->for($source)->create();

        $this->actingAs(quoteOwner(), 'staff')->post("/angebote/{$source->public_id}/duplizieren");

        expect($source->fresh()->status)->toBe(QuoteStatus::Accepted)
            ->and($source->fresh()->items)->toHaveCount(1);
    });
});

describe('deleting a quote', function () {
    it('deletes a draft', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft]);

        $this->actingAs(quoteOwner(), 'staff')
            ->delete("/angebote/{$quote->public_id}")
            ->assertRedirect();

        expect(Quote::find($quote->id))->toBeNull();
    });

    it('refuses to delete a sent quote', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Sent]);

        $this->actingAs(quoteOwner(), 'staff')
            ->delete("/angebote/{$quote->public_id}")
            ->assertStatus(422);

        expect(Quote::find($quote->id))->not->toBeNull();
    });

    it('refuses to delete an accepted quote', function () {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Accepted]);

        $this->actingAs(quoteOwner(), 'staff')
            ->delete("/angebote/{$quote->public_id}")
            ->assertStatus(422);

        expect(Quote::find($quote->id))->not->toBeNull();
    });
});
