<?php

namespace App\Catalog\Http\Controllers;

use App\Catalog\Models\Package;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Was das Studio genau diesem Kunden vorschlägt. Geschrieben im Portal-Reiter
 * des Kontakts, gelesen in seinem Portal.
 *
 * Ein Paket höchstens einmal je Kontakt: updateOrCreate statt create, weil
 * derselbe Vorschlag ein zweites Mal keine zweite Aussage ist, sondern eine
 * geänderte.
 */
class ServiceSuggestionController extends Controller
{
    public function store(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            // Nur aus dem, was im Portal überhaupt angeboten wird: eine
            // Leistung vorzuschlagen, die der Kunde nicht öffnen kann, wäre
            // ein Vorschlag ins Leere.
            'package' => ['required', 'string', Rule::exists('packages', 'public_id')->where('portal_offered', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $package = Package::query()->where('public_id', $validated['package'])->sole();

        $suggestion = ServiceSuggestion::updateOrCreate(
            ['contact_id' => $contact->id, 'package_id' => $package->id],
            ['note' => $validated['note'] ?? null, 'suggested_by_id' => auth('staff')->id()],
        );

        AuditLogger::record(
            event: 'service_suggestion.saved',
            actor: auth('staff')->user(),
            context: ['package' => $package->public_id],
            subject: $suggestion->public_id,
        );

        return back();
    }

    public function destroy(Contact $contact, ServiceSuggestion $serviceSuggestion): RedirectResponse
    {
        $this->authorize('update', $contact);

        abort_unless($serviceSuggestion->contact_id === $contact->id, 404);

        $serviceSuggestion->delete();

        AuditLogger::record(
            event: 'service_suggestion.removed',
            actor: auth('staff')->user(),
            subject: $serviceSuggestion->public_id,
        );

        return back();
    }
}
