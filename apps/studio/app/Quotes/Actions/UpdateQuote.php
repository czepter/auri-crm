<?php

namespace App\Quotes\Actions;

use App\Quotes\Models\Quote;

/**
 * The draft editor's save, shared by the board (App\Quotes\Http\Controllers\
 * QuoteController) and the api surface.
 *
 * Wholesale replace rather than a diffed sync, the same shape
 * PackageController::update() uses and for the same reason: no id
 * anywhere else references a quote item row, so there is no foreign key
 * a delete-and-recreate could orphan.
 */
class UpdateQuote
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'valid_until' => ['nullable', 'date'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'outro' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['nullable', 'in:percentage,amount', 'required_with:discount_value'],
            'discount_value' => ['nullable', 'integer', 'min:1', 'required_with:discount_type'],
            'items' => ['array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percentage,amount', 'required_with:items.*.discount_value'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:1', 'required_with:items.*.discount_type'],
            // Q-08/Q-09/Q-11: der Reglerteil. hourly_rate null laesst alles
            // unten so laufen, wie es vorher lief.
            'hourly_rate' => ['nullable', 'integer', 'min:0'],
            'min_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'max_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'images_per_hour' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            // `id`, nie der Pfad: das Portal liefert dieses Bild an den
            // Kunden aus, und ein aus einem Formular uebernommener Pfad
            // waere das Lesen jeder Datei auf dem Disk — die Vertraege
            // eingeschlossen.
            'items.*.id' => ['nullable', 'string'],
            'items.*.selectable' => ['sometimes', 'boolean'],
            'items.*.selected' => ['sometimes', 'boolean'],
            'items.*.outcome' => ['nullable', 'string', 'max:255'],
            'stages' => ['array'],
            'stages.*.percent' => ['required', 'integer', 'min:0', 'max:100'],
            'stages.*.label' => ['required', 'string', 'max:255'],
            'stages.*.minimum' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @param  array<string, mixed>  $validated  checked against rules() */
    public function __invoke(Quote $quote, array $validated): void
    {
        $quote->update([
            'valid_until' => $validated['valid_until'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'outro' => $validated['outro'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'min_hours' => $validated['min_hours'] ?? null,
            'max_hours' => $validated['max_hours'] ?? null,
            'images_per_hour' => $validated['images_per_hour'] ?? null,
            'hours' => $validated['hours'] ?? null,
        ]);

        // Vor dem Loeschen gelesen: die Zeilen tragen die einzigen Pfade,
        // die dieses Angebot je akzeptiert, und gleich gibt es sie nicht mehr.
        $images = $quote->items()->whereNotNull('image_path')->pluck('image_path', 'public_id')->all();

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
                    'selectable' => $item['selectable'] ?? false,
                    'selected' => $item['selected'] ?? false,
                    'outcome' => $item['outcome'] ?? null,
                    'image_path' => $images[$item['id'] ?? ''] ?? null,
                ])
                ->all()
        );

        // Q-11: dieselbe vollstaendige Ersetzung wie bei den Zeilen. Nichts
        // haelt einen Fremdschluessel auf eine Stufe.
        $quote->paymentStages()->delete();
        $quote->paymentStages()->createMany(
            collect($validated['stages'] ?? [])
                ->values()
                ->map(fn (array $stage, int $index) => [
                    'percent' => $stage['percent'],
                    'label' => $stage['label'],
                    'minimum' => $stage['minimum'] ?? 0,
                    'sort_order' => $index,
                ])
                ->all()
        );

        // Q-15: ein Regler-Angebot hat vor der Zusage keine Summe, sondern
        // eine Spanne. AcceptQuote schreibt total; bis dahin wuerde diese
        // Ableitung eine zweite, falsche Wahrheit hinstellen.
        if (! $quote->fresh()->hasSlider()) {
            $quote->recalculateTotal();
        }
    }
}
