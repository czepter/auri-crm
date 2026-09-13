<?php

namespace App\Portal\Http\Controllers;

use App\Catalog\Data\PortalServiceData;
use App\Catalog\Models\Package;
use App\Http\Controllers\Controller;
use App\Portal\Actions\RequestService;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * „Weitere Leistungen": was ein Bestandskunde über das ansehen und anfragen
 * kann, was er schon gebucht hat.
 *
 * portal.capability:offers liegt vor jeder Route hier, nicht nur vor der
 * Anfrage — anders als bei accept_quotes, wo die Ansicht offen bleibt und nur
 * die Handlung hängt. Dort gibt es etwas zu sehen, das dem Kunden gehört;
 * hier ist das Ansehen selbst schon das Angebot.
 *
 * Was die Middleware nicht weiß, steht in jeder Methode: ob genau dieses
 * Paket im Portal angeboten wird. Geprüft gegen die Zeile, die dieser Request
 * lädt — zwischen Ansicht und Klick kann das Studio sie zurückgezogen haben.
 */
class ServiceController extends Controller
{
    public function show(Package $package, StudioSettingsResolver $studio): Response
    {
        abort_unless($package->portal_offered, 404);

        return Inertia::render('Services/Show', [
            'service' => PortalServiceData::fromModel($package->load('items')),
            'studio' => ['name' => $studio->displayName()],
        ]);
    }

    /**
     * Der Klick, der einen Vorgang anlegt. Geprüft wird gegen die Zeile, die
     * dieser Request lädt, nicht gegen das, was die letzte Ansicht gerendert
     * hat: zwischen beidem kann das Studio die Leistung zurückgezogen haben.
     */
    public function request(
        Request $httpRequest,
        Package $package,
        RequestService $request,
    ): RedirectResponse {
        abort_unless($package->portal_offered, 404);

        $validated = $httpRequest->validate([
            // Nur gefordert, wenn das Paket danach fragt — ein Album hat
            // keinen Termin, und ein Pflichtfeld dafür wäre eine erfundene
            // Angabe im Vorgang.
            'date' => [$package->portal_requires_date ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'venue' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:10000'],
        ]);

        $project = $request(
            auth('portal')->user(),
            $package,
            $validated['date'] ?? null,
            $validated['venue'] ?? null,
            $validated['text'] ?? null,
        );

        return redirect()->route('portal.projects.show', $project->public_id);
    }

    /**
     * Das Bild der Leistung. Über den Controller statt über die Platte, aus
     * demselben Grund wie im Katalog: ein Kunde bekommt nur zu sehen, was
     * sein Studio im Portal anbietet, und nur solange es das tut.
     */
    public function image(Package $package): StreamedResponse
    {
        abort_unless($package->portal_offered && $package->portal_image_path !== null, 404);

        return Storage::disk('local')->response($package->portal_image_path);
    }
}
