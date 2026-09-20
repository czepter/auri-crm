<?php

namespace App\Quotes\Actions;

use App\Documents\Support\InlineHtmlSanitizer;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Models\Quote;
use Illuminate\Validation\Rule;

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
            // Der Editor schickt den Modus immer; die api-Oberflaeche kennt
            // ihn nicht und laesst das Angebot dann, wie es steht.
            'pricing_mode' => ['sometimes', Rule::enum(PricingMode::class)],
            'recipient_contact' => ['nullable', 'string'],
            'recipient_street' => ['nullable', 'string', 'max:255'],
            'recipient_postcode' => ['nullable', 'string', 'max:32'],
            'recipient_city' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'outro' => ['nullable', 'string', 'max:20000'],
            'discount_type' => ['nullable', 'in:percentage,amount', 'required_with:discount_value'],
            'discount_value' => ['nullable', 'integer', 'min:1', 'required_with:discount_type'],
            'discount_hidden' => ['sometimes', 'boolean'],
            'items' => ['array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percentage,amount', 'required_with:items.*.discount_value'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:1', 'required_with:items.*.discount_type'],
            // #412: Basispunkte, leer = der Satz des Angebots. 0 waere ein
            // eigener Satz von null Prozent und ist etwas anderes.
            'items.*.vat_rate' => ['nullable', 'integer', 'min:1', 'max:10000'],
            // Q-08/Q-09/Q-11: der Reglerteil, optional, damit ein Entwurf ohne Stundensatz speichert.
            'hourly_rate' => ['nullable', 'integer', 'min:1'],
            'min_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'max_hours' => ['nullable', 'integer', 'min:1', 'max:2000', 'gte:min_hours'],
            'images_per_hour' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:2000', 'gte:min_hours', 'lte:max_hours'],
            'time_title' => ['nullable', 'string', 'max:255'],
            'time_description' => ['nullable', 'string', 'max:255'],
            // Wo die Zeitzeile zwischen den Positionen steht, 0 = vor der ersten.
            'time_sort_order' => ['nullable', 'integer', 'min:0', 'max:2000'],
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
        $recipientFields = [];

        if (array_key_exists('recipient_contact', $validated)) {
            $recipient = ! empty($validated['recipient_contact'])
                ? $quote->project->contacts()->where('contacts.public_id', $validated['recipient_contact'])->first()
                : null;

            if ($recipient !== null && blank($recipient->street) && blank($recipient->postcode) && blank($recipient->city)) {
                $recipient->update([
                    'street' => $validated['recipient_street'] ?? null,
                    'postcode' => $validated['recipient_postcode'] ?? null,
                    'city' => $validated['recipient_city'] ?? null,
                ]);
            }

            $recipientFields = [
                // Ein vom Projekt geloester Kontakt findet sich hier nicht
                // mehr — den Empfaenger des Angebots kostet das nichts.
                'recipient_contact_id' => empty($validated['recipient_contact'])
                    ? null
                    : ($recipient?->id ?? $quote->recipient_contact_id),
                'recipient_street' => $validated['recipient_street'] ?? null,
                'recipient_postcode' => $validated['recipient_postcode'] ?? null,
                'recipient_city' => $validated['recipient_city'] ?? null,
            ];
        }

        // §4.1: der Modus ist der Schalter, nicht der Stundensatz. Bleibt ein
        // Satz am klassischen Angebot stehen, rendern PDF, Vorschau und Portal
        // einen Regler, den der Editor nicht zeigt. Geleert wird nur auf
        // ausdrueckliche Ansage — ein Aufrufer ohne Modus aendert keinen.
        $mode = $validated['pricing_mode'] ?? $quote->pricing_mode->value;

        $slider = array_key_exists('pricing_mode', $validated) && $mode === PricingMode::Classic->value
            ? array_fill_keys(['hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours'], null)
            : [
                'hourly_rate' => $validated['hourly_rate'] ?? null,
                'min_hours' => $validated['min_hours'] ?? null,
                'max_hours' => $validated['max_hours'] ?? null,
                'images_per_hour' => $validated['images_per_hour'] ?? null,
                'hours' => $validated['hours'] ?? null,
            ];

        $quote->update([
            'valid_until' => $validated['valid_until'] ?? null,
            'pricing_mode' => $mode,
            ...$recipientFields,
            'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
            'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'discount_hidden' => $validated['discount_hidden'] ?? $quote->discount_hidden,
            ...$slider,
            'time_title' => $validated['time_title'] ?? null,
            'time_description' => $validated['time_description'] ?? null,
            'time_sort_order' => $validated['time_sort_order'] ?? $quote->time_sort_order,
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
                    'vat_rate' => $item['vat_rate'] ?? null,
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

        $quote->recalculateTotal();
    }
}
