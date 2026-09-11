<?php

namespace App\Quotes\Http\Controllers;

use App\Catalog\Actions\CreatePackageFromQuote;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Actions\UpdateQuote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Carbon\CarbonInterval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    public function store(Request $request, Project $project, SeedQuoteFromPackage $seed, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorize('manageQuotes', $project);

        $validated = $request->validate([
            'valid_until' => ['nullable', 'date'],
            // Scoped to active packages here rather than left to a 404 from
            // firstOrFail(): a deactivated package dropping out of what a new
            // quote may start from (Package::scopeActive()) is a validation
            // fact about this form's input, not a missing-record error.
            'package' => ['nullable', 'string', Rule::exists('packages', 'public_id')->where('is_active', true)],
            'addendum_to' => ['nullable', 'string'],
        ]);

        // T-05's fallback: Package carries no default_validity_days column
        // today (App\Catalog\Models\Package), so a quote with no explicit
        // valid_until — seeded from a package or not — takes it from the
        // tenant's own default. Read once, at creation, and never again: a
        // later change to the setting is not this quote's concern (see
        // StudioSettingsResolver::quoteValidityDays()).
        $validUntil = $validated['valid_until'] ?? now()->addDays($settings->quoteValidityDays())->toDateString();

        // Der Nachtrag lässt seinen Vorgänger angenommen, anders als die
        // Revision, die ihn auf superseded setzt (ReviseQuote).
        $predecessor = ! empty($validated['addendum_to'])
            ? $project->quotes()->where('public_id', $validated['addendum_to'])
                ->where('status', QuoteStatus::Accepted)->firstOrFail()
            : null;

        $quote = $project->quotes()->create([
            'valid_until' => $validUntil,
            'is_addendum' => $predecessor !== null,
            'predecessor_id' => $predecessor?->id,
        ]);

        if (! empty($validated['package'])) {
            $package = Package::active()->where('public_id', $validated['package'])->firstOrFail();
            $seed($quote, $package);
        }

        AuditLogger::record(
            event: 'quote.created',
            actor: auth('staff')->user(),
            context: ['project' => $project->public_id],
            subject: $quote->public_id,
            projectId: $project->id,
        );

        return back();
    }

    public function update(Request $request, Quote $quote, UpdateQuote $save): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $validated = $request->validate([...UpdateQuote::rules(), 'items.*.remember' => ['boolean']]);

        // Der Schlüssel kommt nur mit, wenn der Bildschirm das Häkchen
        // gerendert hat. Kommt er trotzdem, ist das kein Bedienschritt, den
        // man still verwerfen darf.
        if (collect($validated['items'] ?? [])->contains(fn (array $item) => array_key_exists('remember', $item))
            && Gate::denies('create', Package::class)) {
            abort(403);
        }

        $save($quote, $validated);

        $this->syncCatalog($validated['items'] ?? []);

        AuditLogger::recordThrottled(
            event: 'quote.updated',
            within: CarbonInterval::minutes(15),
            actor: auth('staff')->user(),
            subject: $quote->public_id,
            projectId: $quote->project_id,
        );

        return back();
    }

    /**
     * Q-14: das kopierte Bild einer Baustein-Zeile, für die Anzeige im
     * Editor. Über die Zeile aufgelöst und nicht über den Pfad — dieselbe
     * Regel, die update() oben trägt.
     */
    public function itemImage(Quote $quote, QuoteItem $quoteItem): StreamedResponse
    {
        $this->authorize('view', $quote->project);

        abort_unless($quoteItem->quote_id === $quote->id && $quoteItem->image_path !== null, 404);

        return Storage::disk('local')->response($quoteItem->image_path);
    }

    /**
     * Zwei Prüfungen, weil zwei Dinge berührt werden. Heute hält jede Rolle
     * beides — getrennt geprüft wird aus dem Grund, den PackagePolicy nennt:
     * P6/I-01 gibt Token gegen einzelne Fähigkeiten aus.
     *
     * Bei jedem Status verfügbar, nicht nur beim Entwurf: gelesen wird nur,
     * und das brauchbarste Paket ist das angenommene Angebot.
     */
    public function saveAsPackage(Request $request, Quote $quote, CreatePackageFromQuote $create): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);
        $this->authorize('create', Package::class);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        // Am Namen, weil das Formular kein zweites Feld hat, an dem eine
        // Meldung stehen könnte.
        if ($quote->items->isEmpty()) {
            throw ValidationException::withMessages(['name' => __('quotes.emptyPackage')]);
        }

        $package = $create($quote, $validated['name']);

        AuditLogger::record(
            event: 'package.created',
            actor: auth('staff')->user(),
            context: ['name' => $package->name, 'source' => 'quote'],
            subject: $package->public_id,
        );

        return back();
    }

    /**
     * Q-06's state change: allocates the number, snapshots the VAT and the
     * already-computed amounts, and sends the client mail — all of
     * SendQuote::__invoke(), audit logging included, so nothing repeats here.
     */
    public function send(Quote $quote, SendQuote $send): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $send($quote, auth('staff')->user());

        return back();
    }

    /**
     * Das Häkchen ist keine Spalte auf quote_items, sondern die Frage "gibt es
     * einen Katalogeintrag mit diesem Titel". Eine Zeile ohne den Schlüssel
     * stellt die Frage nicht und lässt die Bibliothek unberührt.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function syncCatalog(array $items): void
    {
        foreach ($items as $item) {
            if (! array_key_exists('remember', $item)) {
                continue;
            }

            if ($item['remember']) {
                CatalogItem::updateOrCreate(
                    ['title' => $item['title']],
                    ['description' => $item['description'] ?? null, 'unit_price' => $item['unit_price'] ?? null],
                );

                continue;
            }

            CatalogItem::where('title', $item['title'])->delete();
        }
    }

    public function download(Quote $quote, RenderQuotePdf $render): Response
    {
        $this->authorize('manageQuotes', $quote->project);

        return response($render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.($quote->number ?? $quote->public_id).'.pdf"',
        ]);
    }
}
