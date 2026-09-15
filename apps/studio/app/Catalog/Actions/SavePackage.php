<?php

namespace App\Catalog\Actions;

use App\Catalog\Enums\PortalPriceMode;
use App\Catalog\Models\Package;
use Illuminate\Validation\Rule;

/**
 * The package editor's save, shared by the board (App\Catalog\Http\
 * Controllers\PackageController) and the api surface.
 *
 * Wholesale replace of the lines rather than a diffed sync: no id anywhere
 * else references an item row — see Package::copyItems() — so there is no
 * foreign key a delete-and-recreate could orphan.
 */
class SavePackage
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],

            'portal_offered' => ['sometimes', 'boolean'],
            'portal_price_mode' => ['sometimes', Rule::enum(PortalPriceMode::class)],
            'portal_requires_date' => ['sometimes', 'boolean'],
            'project_type_key' => [
                'nullable',
                'required_if_accepted:portal_offered',
                Rule::exists('project_types', 'key')->where('is_active', true),
            ],
            'items' => ['array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            // #412: Basispunkte, leer = der Satz des Angebots, auf dem die
            // Zeile landet. Das Paket bringt den Satz mit, statt ihn bei
            // jedem Angebot neu zu verlangen.
            'items.*.vat_rate' => ['nullable', 'integer', 'min:1', 'max:10000'],
            // Q-09/Q-10: the block half of a line. `id` is how a picture
            // survives the wholesale replace below — never the path itself:
            // a stored path accepted from a form is a read of any file on
            // the disk, this app's contracts included.
            'items.*.id' => ['nullable', 'string'],
            'items.*.selectable' => ['sometimes', 'boolean'],
            'items.*.selected' => ['sometimes', 'boolean'],
            'items.*.outcome' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @param  array<string, mixed>  $validated  checked against rules() */
    public function __invoke(Package $package, array $validated): Package
    {
        $package->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            ...array_intersect_key($validated, [
                'is_active' => true,
                'portal_offered' => true,
                'portal_price_mode' => true,
                'portal_requires_date' => true,
                'project_type_key' => true,
            ]),
        ])->save();

        // Read before the delete below: the rows carry the only paths this
        // app will accept, and they are about to stop existing. Resolved from
        // this package's own rows, never from the payload — an id the client
        // made up simply finds nothing.
        $images = $package->items()->whereNotNull('image_path')->pluck('image_path', 'public_id')->all();

        $package->items()->delete();
        // sort_order comes from list position rather than a field someone
        // fills in: the admin screen reorders by moving a row, not by typing
        // a number.
        $package->items()->createMany(
            collect($validated['items'] ?? [])->values()->map(fn (array $item, int $index) => [
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? null,
                'vat_rate' => $item['vat_rate'] ?? null,
                'sort_order' => $index,
                'selectable' => $item['selectable'] ?? false,
                'selected' => $item['selected'] ?? false,
                'outcome' => $item['outcome'] ?? null,
                'image_path' => $images[$item['id'] ?? ''] ?? null,
            ])->all()
        );

        return $package;
    }
}
