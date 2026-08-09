<?php

namespace App\Quotes\Http\Controllers;

use App\Catalog\Models\Package;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        ]);

        // T-05's fallback: Package carries no default_validity_days column
        // today (App\Catalog\Models\Package), so a quote with no explicit
        // valid_until — seeded from a package or not — takes it from the
        // tenant's own default. Read once, at creation, and never again: a
        // later change to the setting is not this quote's concern (see
        // StudioSettingsResolver::quoteValidityDays()).
        $validUntil = $validated['valid_until'] ?? now()->addDays($settings->quoteValidityDays())->toDateString();

        $quote = $project->quotes()->create([
            'valid_until' => $validUntil,
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
        );

        return back();
    }

    /**
     * Wholesale replace rather than a diffed sync, the same shape
     * PackageController::update() uses and for the same reason: no id
     * anywhere else references a quote item row, so there is no foreign key
     * a delete-and-recreate could orphan.
     */
    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $validated = $request->validate([
            'valid_until' => ['nullable', 'date'],
            'discount_type' => ['nullable', 'in:percentage,amount', 'required_with:discount_value'],
            'discount_value' => ['nullable', 'integer', 'min:1', 'required_with:discount_type'],
            'items' => ['array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percentage,amount', 'required_with:items.*.discount_value'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:1', 'required_with:items.*.discount_type'],
        ]);

        $quote->update([
            'valid_until' => $validated['valid_until'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
        ]);

        $quote->items()->delete();
        $quote->items()->createMany(
            collect($validated['items'] ?? [])
                ->values()
                ->map(fn (array $item, int $index) => [
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? null,
                    'sort_order' => $index,
                    'discount_type' => $item['discount_type'] ?? null,
                    'discount_value' => $item['discount_value'] ?? null,
                ])
                ->all()
        );

        $quote->recalculateTotal();

        AuditLogger::record(
            event: 'quote.updated',
            actor: auth('staff')->user(),
            subject: $quote->public_id,
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
}
