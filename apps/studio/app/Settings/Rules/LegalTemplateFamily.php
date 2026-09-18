<?php

namespace App\Settings\Rules;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Eine hinterlegte Vorlagen-Familie muss ein Rechtstext dieses Studios sein.
 * Ohne die Prüfung zeigte das Portal eine Seite, die niemand geschrieben hat —
 * oder gar keine, was bei AGB und Widerrufsbelehrung dasselbe Problem ist.
 */
class LegalTemplateFamily implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $exists = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Legal)
            ->where('family_id', $value)
            ->exists();

        if (! $exists) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
