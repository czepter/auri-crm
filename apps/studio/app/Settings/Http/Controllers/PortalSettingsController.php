<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Portal\Http\Controllers\LegalTextController;
use App\Settings\Rules\LegalTemplateFamily;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-08's "Portal" pane, mockup 14's own "bald" placeholder finally wired
 * live. One form, one save — like Studio-Profil (T-02) and Zahlungen
 * (T-07), unlike Zahlungsdaten & USt's own split endpoint: nothing here
 * needs a fieldset-level save boundary.
 *
 * The maintenance switch is not a third A-04 revocation path — it never
 * touches `project_contacts` or `contacts.portal_access_revoked_at`, only
 * this table's own `portal_maintenance_enabled`/`_message` columns, read by
 * EnsurePortalMaintenanceClosure alone.
 */
class PortalSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Portal', [
            'settings' => PortalSettingsData::fromResolver($settings),
            // The pane's preview draws the portal head, which is the
            // studio's logo, pair and accent — none of which belongs in
            // PortalSettingsData, all of which this DTO already carries.
            'branding' => StudioSettingsData::fromResolver($settings),
            // Die Rechtstexte, die das Studio selbst geschrieben hat — als
            // Familie, weil die Zeile auf die jeweils aktuelle Fassung zeigt.
            'legalTemplates' => $this->legalTemplates(),
        ]);
    }

    /** @return list<array{family: string, name: string, updatedAt: ?string}> */
    private function legalTemplates(): array
    {
        return DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Legal)
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentTemplate $template) => [
                'family' => $template->family_id,
                'name' => $template->name,
                'updatedAt' => $template->updated_at?->toDateString(),
            ])
            ->all();
    }

    /**
     * Die Portal-Startseite selbst, für den iframe neben dem Formular. Sie
     * steht auf der Admin-Oberfläche und nicht hinter einer Portal-Sitzung:
     * es gibt hier keinen Kunden, dessen Portal gezeigt werden könnte, und
     * das Studio soll nicht erst einen suchen müssen, um die eigene
     * Begrüßung zu sehen.
     *
     * Die Marke, die Rechtstexte und die Anrede kommen als dieselben
     * geteilten Props herein, die HandleInertiaRequests der Portal-Oberfläche
     * gibt — die Bauteile des Portals lesen sie dort.
     */
    public function preview(Request $request, StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        $state = $request->query('zustand');

        // Getippt statt gespeichert: das Formular schickt, was gerade im Feld
        // steht, damit die Vorschau mitläuft.
        $welcome = $request->query('text', match ($state) {
            'inquiry' => $settings->portalWelcomeInquiry(),
            'done' => $settings->portalWelcomeDone(),
            default => $settings->portalWelcomeActive(),
        });

        return Inertia::render('Settings/PortalHomePreview', [
            'welcomeText' => $welcome,
            'closed' => $request->boolean('geschlossen'),
            'maintenanceMessage' => $request->query('hinweis', $settings->maintenanceMessage()),
            'portalBranding' => [
                'displayName' => $settings->displayName() ?? config('app.name'),
                'logoUrl' => $settings->logoPath() !== null ? '/branding/logo' : null,
                'accentColor' => $settings->attributes()['accent_color'],
                'address' => $request->query('kontakt'),
            ],
            'portalLegal' => collect(LegalTextController::TEXTS)
                ->mapWithKeys(fn (string $text) => [$text.'Url' => $settings->legalUrl($text)])
                ->all(),
            'portalNav' => null,
        ]);
    }

    /**
     * Impressum, Datenschutz, AGB und Widerruf — plus wohin eine
     * Datenschutz-Anfrage geht. Steht unter Abrechnung statt unter Portal:
     * ohne Widerrufsbelehrung geht kein Angebot hinaus, mit oder ohne Portal.
     */
    public function legal(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/LegalTexts', [
            'settings' => PortalSettingsData::fromResolver($settings),
            'legalTemplates' => $this->legalTemplates(),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_maintenance_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_maintenance_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_legal_datenschutz_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_agb_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_impressum_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_widerruf_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            // Die Vorlage wird als Familie gespeichert, damit das Portal die
            // jeweils aktuelle Fassung zeigt; exists() prüft gegen die
            // Rechtstexte des Studios.
            'portal_legal_impressum_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_datenschutz_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_agb_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_widerruf_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_accept_quotes_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_sign_contracts_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_payment_status_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_offers_enabled' => ['sometimes', 'required', 'boolean'],
            // Was der Kunde in einer Galerie darf, ist dieselbe Sorte
            // Freigabe wie die vier darüber — die Vorgaben einer neuen
            // Galerie (Frist, Gestaltung) bleiben auf /galerien.
            'gallery_default_downloads_enabled' => ['sometimes', 'required', 'boolean'],
            'gallery_default_lists_enabled' => ['sometimes', 'required', 'boolean'],
            'gallery_default_comments_enabled' => ['sometimes', 'required', 'boolean'],
            // P-09 (#228): everything Sprint 25's seven portal screens do
            // that T-08's four switches never described.
            'portal_messaging_mode' => ['sometimes', 'required', 'in:off,booking,any'],
            'portal_welcome_inquiry' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_welcome_active' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_welcome_done' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'dsgvo_request_recipient' => ['sometimes', 'nullable', 'email', 'max:255'],
            'dsgvo_request_deadline_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'portal_contact_phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'portal_contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'portal_contact_response_time' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return back();
    }
}
