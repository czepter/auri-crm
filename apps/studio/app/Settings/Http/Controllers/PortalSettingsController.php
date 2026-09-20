<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use Carbon\CarbonImmutable;
use App\Http\Controllers\Controller;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Portal\Http\Controllers\LegalTextController;
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
        ]);
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

        $state = in_array($request->query('zustand'), ['inquiry', 'active', 'done'], true)
            ? $request->query('zustand')
            : 'inquiry';

        // Getippt statt gespeichert: das Formular schickt, was gerade im Feld
        // steht, damit die Vorschau mitläuft.
        $welcome = $request->query('text', match ($state) {
            'inquiry' => $settings->portalWelcomeInquiry(),
            'done' => $settings->portalWelcomeDone(),
            default => $settings->portalWelcomeActive(),
        });

        return Inertia::render('Settings/PortalHomePreview', [
            'state' => $state,
            'bookings' => $this->sampleBookings($state),
            'pending' => $this->samplePending($state),
            // Eine erfundene Kundin, damit die Seite jemandem gehört: ein
            // echter Kontakt müsste erst gesucht werden, und seine Buchungen
            // sagen über die Einstellung nichts.
            'client' => 'Maxi Musterfrau',
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
     * Die Buchungen der Musterkundin, gerechnet ab heute: ein laufender
     * Termin, der in der Vergangenheit liegt, wäre keiner.
     *
     * @return list<array{title: string, note: string, state: string, aside: ?string, year: string}>
     */
    private function sampleBookings(string $state): array
    {
        $now = CarbonImmutable::now();

        return match ($state) {
            'inquiry' => [],
            'done' => [
                $this->booking('Hochzeit im Gut Wehlitz', $now->subMonths(4), 'done', 'Bilder liegen bereit'),
                $this->booking('Paarshooting Auwald', $now->subMonths(10), 'done', 'Bilder liegen bereit'),
            ],
            default => [
                $this->booking('Hochzeit im Gut Wehlitz', $now->addMonths(3), 'open', null, '2.400,00 €'),
                $this->booking('Standesamt Leipzig', $now->addWeeks(5), 'neutral'),
            ],
        };
    }

    /** @return array{title: string, note: string, state: string, aside: ?string, year: string} */
    private function booking(string $title, CarbonImmutable $date, string $state, ?string $note = null, ?string $aside = null): array
    {
        return [
            'title' => $title,
            'note' => $note ?? $date->format('d.m.Y'),
            'state' => $state,
            'aside' => $aside,
            'year' => $date->format('Y'),
        ];
    }

    /**
     * Was auf die Kundin wartet: vor der Buchung das Angebot, danach die
     * nächste Rate, und wenn alles geliefert ist, nichts.
     *
     * @return list<array{title: string, note: string, aside: string}>
     */
    private function samplePending(string $state): array
    {
        $now = CarbonImmutable::now();

        return match ($state) {
            'inquiry' => [[
                'title' => 'Angebot',
                'note' => 'Gültig bis '.$now->addDays(14)->format('d.m.Y'),
                'aside' => '3.900,00 €',
            ]],
            'done' => [],
            default => [[
                'title' => 'Rate',
                'note' => 'Fällig am '.$now->addDays(10)->format('d.m.Y'),
                'aside' => '1.200,00 €',
            ]],
        };
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_maintenance_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_maintenance_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
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
