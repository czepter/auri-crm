<?php

namespace App\Integration\Http\Controllers;

use App\Catalog\Actions\SavePackage;
use App\Catalog\Data\PackageData;
use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The catalogue on the api surface, through the same SavePackage the board
 * uses. update() is a merge, unlike the board's wholesale replace: a caller
 * that sends only `name` must not drop the lines a screen built.
 *
 * The pictures stay out. A line keeps the one it has (SavePackage resolves it
 * from the row's own id), but uploading one is the board's job — a file is not
 * something this surface carries.
 */
class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = Package::query()->with('items')->orderBy('name')->get();

        return response()->json(['data' => $packages->map(PackageData::fromModel(...))->all()]);
    }

    public function store(Request $request, SavePackage $save): JsonResponse
    {
        $package = $save(new Package, $request->validate(SavePackage::rules()));

        AuditLogger::record(
            event: 'package.created',
            actor: $this->actor($request),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        return $this->answer($package, 201);
    }

    public function update(Request $request, Package $package, SavePackage $save): JsonResponse
    {
        $validated = $request->validate([...SavePackage::rules(), 'name' => ['sometimes', 'string', 'max:255']]);

        $save($package, [...$this->current($package), ...$validated]);

        AuditLogger::record(
            event: 'package.updated',
            actor: $this->actor($request),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        return $this->answer($package);
    }

    /**
     * Nothing built from a package references it — a quote takes a dead copy
     * of the lines (Package::copyItems()) — so this removes the package and
     * its own rows and touches nothing else. Deactivating instead keeps it
     * off the list new quotes start from without removing it.
     */
    public function destroy(Request $request, Package $package): Response
    {
        $package->delete();

        AuditLogger::record(
            event: 'package.deleted',
            actor: $this->actor($request),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        return response()->noContent();
    }

    /** @return array<string, mixed> the package as SavePackage::rules() would receive it */
    private function current(Package $package): array
    {
        return [
            ...$package->only(['name', 'description', 'is_active']),
            'items' => $package->items->map(fn (PackageItem $item) => [
                ...$item->only(['title', 'description', 'quantity', 'unit_price', 'selectable', 'selected', 'outcome']),
                'id' => $item->public_id,
            ])->all(),
        ];
    }

    private function answer(Package $package, int $status = 200): JsonResponse
    {
        return response()->json(['data' => PackageData::fromModel($package->fresh(['items']))], $status);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
