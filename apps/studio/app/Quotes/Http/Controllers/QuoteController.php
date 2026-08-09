<?php

namespace App\Quotes\Http\Controllers;

use App\Catalog\Models\Package;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function store(Request $request, Project $project, SeedQuoteFromPackage $seed): RedirectResponse
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

        $quote = $project->quotes()->create([
            'valid_until' => $validated['valid_until'] ?? null,
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
            'items' => ['array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
        ]);

        $quote->update(['valid_until' => $validated['valid_until'] ?? null]);

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
}
