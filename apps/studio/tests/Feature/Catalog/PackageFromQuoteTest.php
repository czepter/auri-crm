<?php

use App\Catalog\Models\Package;
use App\Models\AuditLog;
use App\Models\User;
use App\Quotes\Actions\CreatePackageFromQuote;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('copies title, description, quantity and unit price in order', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'description' => 'Ganztags', 'quantity' => 1, 'unit_price' => 189000, 'sort_order' => 0]);
    QuoteItem::factory()->for($quote)->create(['title' => 'Zweitfotograf', 'description' => null, 'quantity' => 2, 'unit_price' => 49000, 'sort_order' => 1]);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->name)->toBe('Hochzeit Basis')
        ->and($package->is_active)->toBeTrue()
        ->and($package->items->pluck('title')->all())->toBe(['Reportage', 'Zweitfotograf'])
        ->and($package->items->first()->description)->toBe('Ganztags')
        ->and($package->items->last()->quantity)->toBe(2)
        ->and($package->items->pluck('sort_order')->all())->toBe([0, 1]);
});

it('drops the line discount, because a package holds services and not negotiations', function () {
    $quote = Quote::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 1000]);
    QuoteItem::factory()->for($quote)->create([
        'title' => 'Reportage',
        'quantity' => 1,
        'unit_price' => 189000,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 500,
    ]);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->items->first()->unit_price)->toBe(189000)
        ->and($package->items->first()->getAttributes())->not->toHaveKey('discount_type');
});

it('keeps a line without a price as an included service', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->included()->create(['title' => 'Anfahrt']);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->items->first()->unit_price)->toBeNull();
});

it('carries the slider and the payment stages back into the catalogue', function () {
    $quote = Quote::factory()->create([
        'hourly_rate' => 25000,
        'min_hours' => 4,
        'max_hours' => 10,
        'images_per_hour' => 40,
        'hours' => 6,
    ]);
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);
    $quote->paymentStages()->create(['percent' => 30, 'label' => 'Anzahlung', 'minimum' => 50000, 'sort_order' => 0]);
    $quote->paymentStages()->create(['percent' => 70, 'label' => 'Restzahlung', 'sort_order' => 1]);

    $package = app(CreatePackageFromQuote::class)($quote->fresh(), 'Hochzeit Basis');

    expect($package->hourly_rate)->toBe(25000)
        ->and($package->min_hours)->toBe(4)
        ->and($package->max_hours)->toBe(10)
        ->and($package->images_per_hour)->toBe(40)
        ->and($package->hours)->toBe(6)
        ->and($package->paymentStages->pluck('label')->all())->toBe(['Anzahlung', 'Restzahlung'])
        ->and($package->paymentStages->first()->minimum)->toBe(50000);
});

it('saves a quote as a package over the route, and logs where it came from', function () {
    $user = User::factory()->withMfa()->create();
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/als-paket", ['name' => 'Hochzeit Basis'])
        ->assertRedirect();

    $package = Package::sole();

    expect($package->name)->toBe('Hochzeit Basis')
        ->and($package->items)->toHaveCount(1);

    $row = AuditLog::where('event', 'package.created')->sole();

    expect($row->subject)->toBe($package->public_id)
        ->and($row->context['source'])->toBe('quote');
});

// Available at every status: the action only reads, and the most useful
// package is the accepted quote.
it('saves a quote that has already been sent', function () {
    $quote = Quote::factory()->create(['status' => QuoteStatus::Sent, 'number' => 'AN-2026-001']);
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->post("/angebote/{$quote->public_id}/als-paket", ['name' => 'Hochzeit Basis'])
        ->assertRedirect();

    expect(Package::count())->toBe(1);
});

// Both land on name: the form holds no second field a message could sit at.
it('refuses an empty name and a quote without lines', function () {
    $user = User::factory()->withMfa()->create();
    $empty = Quote::factory()->create();
    $filled = Quote::factory()->create();
    QuoteItem::factory()->for($filled)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 189000]);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$filled->public_id}/als-paket", ['name' => ''])
        ->assertInvalid(['name']);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$empty->public_id}/als-paket", ['name' => 'Leer'])
        ->assertInvalid(['name']);

    expect(Package::count())->toBe(0);
});
