<?php

use App\Catalog\Models\CatalogItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a library line with a public id and no relations', function () {
    $item = CatalogItem::create(['title' => 'Zweitfotograf', 'description' => null, 'unit_price' => 49000]);

    expect($item->public_id)->not->toBeEmpty()
        ->and($item->tenant_id)->not->toBeNull()
        ->and($item->unit_price)->toBe(49000);
});

it('keeps a line without a price as an included service rather than as zero', function () {
    $item = CatalogItem::create(['title' => 'Anfahrt', 'unit_price' => null]);

    expect($item->unit_price)->toBeNull();
});

it('refuses a second line with the same title', function () {
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    expect(fn () => CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 52000]))
        ->toThrow(QueryException::class);
});
