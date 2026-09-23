<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use App\Settings\Rules\ValidFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wer sonst noch an einer Anfrage hängt: Partner, Zahler, die Planerin des
 * Paares. Bis hierher gab es das nur auf der API-Oberfläche
 * (Integration\ProjectContactController) — auf dem Fallbildschirm stand eine
 * 238 Pixel schmale Spalte, in der sich nichts anlegen ließ.
 *
 * Die Rolle ist Freitext. Die Aufzählung bleibt die Liste der Vorschläge und
 * die Heimat der einen Rolle, an der ein Index hängt: auf case_contacts
 * liegt ein partieller Unique-Index über 'primary', und wer bucht, entscheidet
 * die Erfassung.
 */
class LeadContactController extends Controller
{
    public function store(Request $request, ClientCase $case): RedirectResponse
    {
        $this->authorize('manageShoots', $case);

        $validated = $request->validate([
            'contact_id' => ['nullable', 'string', 'size:24', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64', new ValidFormat('phone')],
            'role' => ['nullable', 'string', 'max:60'],
        ]);

        $role = $validated['role'] ?? CaseContactRole::Partner->label();

        abort_if($this->isPrimary($role), 422);

        $contact = $this->resolveContact($validated);

        // unique(case_id, contact_id, role) würde beim zweiten Anhängen
        // werfen.
        if (! $case->contacts()->whereKey($contact->id)->wherePivot('role', $role)->exists()) {
            $case->attachContact($contact, $role);
        }

        return back();
    }

    public function destroy(ClientCase $case, Contact $contact): RedirectResponse
    {
        $this->authorize('manageShoots', $case);

        $role = $case->contacts()->whereKey($contact->id)->first()?->pivot->role;

        abort_if($role === null || $this->isPrimary($role), 422);

        $case->contacts()->detach($contact->id);

        return back();
    }

    /**
     * Der Hauptkontakt heißt technisch 'primary' und wird beim Erfassen so
     * angelegt; seine Beschriftung ist, was auf dem Bildschirm steht. Beide
     * Schreibweisen zeigen auf dieselbe Rolle und sind deshalb beide gesperrt.
     */
    private function isPrimary(string $role): bool
    {
        return $role === CaseContactRole::Primary->value
            || $role === CaseContactRole::Primary->label();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveContact(array $validated): Contact
    {
        if (filled($validated['contact_id'] ?? null)) {
            return Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail();
        }

        // Dieselbe Regel wie CaptureInquiry: eine bekannte Adresse ist diese
        // Person, nicht eine zweite mit demselben Namen.
        $existing = filled($validated['email'] ?? null)
            ? Contact::query()->where('email', $validated['email'])->first()
            : null;

        return $existing ?? Contact::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);
    }
}
