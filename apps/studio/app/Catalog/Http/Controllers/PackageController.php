<?php

namespace App\Catalog\Http\Controllers;

use App\Catalog\Data\PackageData;
use App\Catalog\Models\Package;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Package::class);

        $packages = Package::query()
            ->with('items')
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return Inertia::render('Packages/Index', [
            'packages' => PackageData::collect($packages),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Package::class);

        return Inertia::render('Packages/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Package::class);

        $validated = $this->validated($request);

        $package = Package::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $package->items()->createMany($this->itemRows($validated['items']));

        AuditLogger::record(
            event: 'package.created',
            actor: auth('staff')->user(),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        return redirect('/pakete');
    }

    public function edit(Package $package): Response
    {
        $this->authorize('update', $package);

        return Inertia::render('Packages/Edit', [
            'package' => PackageData::fromModel($package),
        ]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $validated = $this->validated($request);

        $package->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        // Wholesale replace rather than a diffed sync: no id anywhere else
        // references an item row — see Package::copyItems() — so there is no
        // foreign key a delete-and-recreate could orphan.
        $package->items()->delete();
        $package->items()->createMany($this->itemRows($validated['items']));

        AuditLogger::record(
            event: 'package.updated',
            actor: auth('staff')->user(),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        return redirect('/pakete');
    }

    /**
     * is_active wird übernommen: dupliziert wird, um zu ändern, und die
     * Entscheidung über die Sichtbarkeit trifft man danach. Der Name
     * kollidiert mit nichts — packages trägt keinen Unique-Index darauf —, und
     * mehrfaches Duplizieren zeigt sich am Namen selbst an.
     */
    public function duplicate(Package $package): RedirectResponse
    {
        $this->authorize('create', Package::class);

        $copy = $package->replicate(['public_id']);
        $copy->name = "{$package->name} (Kopie)";
        $copy->save();

        $copy->items()->createMany(
            collect($package->copyItems())
                ->values()
                ->map(fn (array $item, int $index) => [...$item, 'sort_order' => $index])
                ->all()
        );

        AuditLogger::record(
            event: 'package.created',
            actor: auth('staff')->user(),
            context: ['name' => $copy->name, 'source' => 'duplicate'],
            subject: $copy->public_id,
        );

        return redirect("/pakete/{$copy->public_id}");
    }

    /** Hides the package from the set a new quote may start from. Nothing already built from it moves. */
    public function toggle(Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $package->update(['is_active' => ! $package->is_active]);

        AuditLogger::record(
            event: $package->is_active ? 'package.activated' : 'package.deactivated',
            actor: auth('staff')->user(),
            subject: $package->public_id,
        );

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * sort_order comes from list position rather than a field someone fills
     * in: the admin screen reorders by moving a row, not by typing a number.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function itemRows(array $items): array
    {
        return collect($items)->values()->map(fn (array $item, int $index) => [
            'title' => $item['title'],
            'description' => $item['description'] ?? null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'] ?? null,
            'sort_order' => $index,
        ])->all();
    }
}
