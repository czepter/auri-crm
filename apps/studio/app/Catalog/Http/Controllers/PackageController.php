<?php

namespace App\Catalog\Http\Controllers;

use App\Catalog\Data\PackageData;
use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        // Read before the delete below: the rows carry the only paths this
        // app will accept, and they are about to stop existing.
        $images = $this->imagePathsFor($package);

        $package->items()->delete();
        $package->items()->createMany($this->itemRows($validated['items'], $images));

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

    /**
     * Q-10: the picture on a block's tile, stored under catalog/{tenant}/ —
     * the same road the logo takes, no media library and no image model.
     * Kept off update() because that one deletes and recreates every row: an
     * upload has to land on a row that exists right now, and the screen
     * carries the path back into the next save.
     */
    public function uploadImage(Request $request, Package $package, PackageItem $packageItem): RedirectResponse
    {
        $this->authorize('update', $package);

        abort_unless($packageItem->package_id === $package->id, 404);

        $request->validate(['image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:4096']]);

        $old = $packageItem->image_path;
        $path = $request->file('image')->store("catalog/{$package->tenant_id}", 'local');

        if ($old !== null && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $packageItem->update(['image_path' => $path]);

        return back();
    }

    /** Behind the staff gate: a catalogue picture is not published until a quote carries a copy of it. */
    public function image(Package $package, PackageItem $packageItem): StreamedResponse
    {
        $this->authorize('view', $package);

        abort_unless($packageItem->package_id === $package->id && $packageItem->image_path !== null, 404);

        return Storage::disk('local')->response($packageItem->image_path);
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
            // Q-09/Q-10: the block half of a line. `id` is how a picture
            // survives update()'s wholesale replace — never the path itself:
            // a stored path accepted from a form is a read of any file on
            // the disk, this app's contracts included. See imagePathsFor().
            'items.*.id' => ['nullable', 'string'],
            'items.*.selectable' => ['sometimes', 'boolean'],
            'items.*.selected' => ['sometimes', 'boolean'],
            'items.*.outcome' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * sort_order comes from list position rather than a field someone fills
     * in: the admin screen reorders by moving a row, not by typing a number.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string>  $images  public_id => stored path, from imagePathsFor()
     * @return list<array<string, mixed>>
     */
    private function itemRows(array $items, array $images = []): array
    {
        return collect($items)->values()->map(fn (array $item, int $index) => [
            'title' => $item['title'],
            'description' => $item['description'] ?? null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'] ?? null,
            'sort_order' => $index,
            'selectable' => $item['selectable'] ?? false,
            'selected' => $item['selected'] ?? false,
            'outcome' => $item['outcome'] ?? null,
            // Resolved from this package's own rows, never from the payload:
            // an id the client made up simply finds nothing.
            'image_path' => $images[$item['id'] ?? ''] ?? null,
        ])->all();
    }

    /**
     * The picture each of this package's current lines holds, keyed by the
     * public_id the form sends back. The whole point is that a path only
     * ever comes from a row this tenant already owns — uploadImage() builds
     * the only other one, out of a file and the tenant id, and neither is
     * the client's to name.
     *
     * @return array<string, string>
     */
    private function imagePathsFor(Package $package): array
    {
        return $package->items()
            ->whereNotNull('image_path')
            ->pluck('image_path', 'public_id')
            ->all();
    }
}
