<?php

namespace App\Catalog\Http\Controllers;

use App\Catalog\Actions\SavePackage;
use App\Catalog\Data\CatalogItemData;
use App\Catalog\Data\PackageData;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectType;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
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
            ->withCount(['items', ...self::requestCounts()])
            ->orderBy('name')
            ->get();

        return Inertia::render('Packages/Index', [
            'packages' => PackageData::collect($packages),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Package::class);

        return Inertia::render('Packages/Create', [
            'catalogItems' => self::catalogItems(),
        ]);
    }

    public function store(Request $request, SavePackage $save): RedirectResponse
    {
        $this->authorize('create', Package::class);

        $package = $save(new Package, $request->validate(SavePackage::rules()));

        AuditLogger::record(
            event: 'package.created',
            actor: auth('staff')->user(),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        return redirect('/pakete');
    }

    public function edit(Package $package, StudioSettingsResolver $studio): Response
    {
        $this->authorize('update', $package);

        $package->loadCount(self::requestCounts());

        return Inertia::render('Packages/Edit', [
            'package' => PackageData::fromModel($package),
            'catalogItems' => self::catalogItems(),
            // Wie ServiceController::show(): ein Kleinunternehmer hat keinen
            // Satz, und dann steht unter dem Preis nichts.
            'vatRate' => $studio->effectiveVatRate() ?: null,
            // Ohne Projekttyp hat eine Anfrage aus dem Portal keine Form —
            // siehe App\Portal\Actions\RequestService.
            'projectTypes' => ProjectType::query()->active()
                ->orderBy('sort_order')->orderBy('name')
                ->get(['key', 'name'])->all(),
            // Die letzten Anfragen, die von diesem Paket ausgingen. Steht hier
            // und nicht in der Übersicht: die Frage gilt diesem Paket.
            'requests' => $package->leads()
                ->with(['case.contacts', 'case.primaryShoot'])
                ->orderByDesc('captured_at')
                ->limit(10)
                ->get()
                ->map(fn (Lead $lead) => [
                    'id' => $lead->case->public_id,
                    'project' => $lead->case->public_id,
                    'contact' => $lead->case->contactNames(),
                    'requestedOn' => $lead->captured_at?->format('Y-m-d'),
                    'date' => $lead->case->primaryShoot?->date?->format('Y-m-d'),
                    'booked' => $lead->case->isBooked(),
                ])->all(),
        ]);
    }

    public function update(Request $request, Package $package, SavePackage $save): RedirectResponse
    {
        $this->authorize('update', $package);

        $save($package, $request->validate(SavePackage::rules()));

        AuditLogger::record(
            event: 'package.updated',
            actor: auth('staff')->user(),
            context: ['name' => $package->name],
            subject: $package->public_id,
        );

        // Wie Angebote (QuoteController::update): der Autosave feuert bei
        // jeder Änderung und würde von einem festen Redirect ins Leere
        // laufen — zurück auf dieselbe Seite ist, was ein Autosave braucht.
        return back();
    }

    /**
     * Positionen und Vorschläge fallen per Fremdschlüssel mit (cascadeOnDelete);
     * eine Anfrage, die von hier ausging, bleibt stehen und verliert nur die
     * Referenz (leads.package_id ist nullOnDelete, siehe die Migration).
     */
    public function destroy(Package $package): RedirectResponse
    {
        $this->authorize('delete', $package);

        $name = $package->name;
        $package->delete();

        AuditLogger::record(
            event: 'package.deleted',
            actor: auth('staff')->user(),
            context: ['name' => $name],
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
    /**
     * Q-10: die Schaufensterbilder des Pakets — der Startsatz, den
     * SeedQuoteFromPackage in jedes daraus gebaute Angebot kopiert.
     * Angehängt statt ersetzt, ein Satz ist, was das Raster braucht.
     */
    public function storeShowcase(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $request->validate(['image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:4096']]);

        $package->addShowcaseImage($request->file('image'));

        return back();
    }

    public function destroyShowcase(Package $package, int $index): RedirectResponse
    {
        $this->authorize('update', $package);

        $package->removeShowcaseImage($index);

        return back();
    }

    /** Indiziert statt benannt, damit kein gespeicherter Dateiname in eine URL gerät. */
    public function showcase(Package $package, int $index): StreamedResponse
    {
        $this->authorize('view', $package);

        $path = $package->showcasePaths()[$index] ?? null;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }

    public function image(Package $package, PackageItem $packageItem): StreamedResponse
    {
        $this->authorize('view', $package);

        abort_unless($packageItem->package_id === $package->id && $packageItem->image_path !== null, 404);

        return Storage::disk('local')->response($packageItem->image_path);
    }

    /** @return CatalogItemData[] */
    private static function catalogItems()
    {
        return CatalogItemData::collect(CatalogItem::query()->orderBy('title')->get());
    }

    /**
     * Gebucht heißt: der Vorgang hat einen Buchungszeitpunkt. Dieselbe Auskunft, die
     * Lead::scopeUnbooked() umgekehrt gibt.
     *
     * @return array<int|string, string|\Closure>
     */
    private static function requestCounts(): array
    {
        return [
            'leads as requested_count',
            'leads as booked_count' => fn (Builder $query) => $query->whereHas('case', fn ($project) => $project->booked()),
        ];
    }
}
