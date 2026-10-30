<?php

namespace App\Settings\Http\Controllers;

use App\Catalog\Data\PortalServiceData;
use App\Catalog\Models\Package;
use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Portal\Support\LegalTexts;
use App\Settings\Actions\UpdatePortalContactAvatar;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Enums\FontPair;
use App\Settings\Rules\LegalTemplateFamily;
use App\Settings\Rules\ValidFormat;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-08's "Portal" pane, mockup 14's own "bald" placeholder finally wired
 * live. One form, one save — like Studio-Profil (T-02) and Zahlungen
 * (T-07), unlike Zahlungsdaten & USt's own split endpoint: nothing here
 * needs a fieldset-level save boundary.
 *
 * The maintenance switch is not a third A-04 revocation path — it never
 * touches `case_contacts` or `contacts.portal_access_revoked_at`, only
 * this table's own `portal_maintenance_enabled`/`_message` columns, read by
 * EnsurePortalMaintenanceClosure alone.
 */
class PortalSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Portal', [
            'settings' => PortalSettingsData::current(),
            // The pane's preview draws the portal head, which is the
            // studio's logo, pair and accent — none of which belongs in
            // PortalSettingsData, all of which this DTO already carries.
            'branding' => StudioSettingsData::current(),
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
    public function preview(Request $request): Response
    {
        $this->authorizeSettings();

        $state = in_array($request->query('zustand'), ['inquiry', 'active', 'done'], true)
            ? $request->query('zustand')
            : 'inquiry';

        // Getippt statt gespeichert: das Formular schickt, was gerade im Feld
        // steht, damit die Vorschau mitläuft.
        $welcome = $request->query('text', match ($state) {
            'inquiry' => StudioSettings::current()->portal_welcome_inquiry,
            'done' => StudioSettings::current()->portal_welcome_done,
            default => StudioSettings::current()->portal_welcome_active,
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
            'shelf' => $request->boolean('leistungen', (bool) StudioSettings::current()->portal_offers_enabled) ? $this->sampleShelf() : [],
            'maintenanceMessage' => $request->query('hinweis', StudioSettings::current()->portal_maintenance_message),
            'fontPair' => (FontPair::tryFrom((string) $request->query('schrift')) ?? FontPair::current())->value,
            // Getippt schlägt gespeichert, wie beim Text: die Seite Allgemein
            // schickt Name, Farbe, Logo und Anschrift, wie sie im Formular stehen.
            'portalBranding' => [
                'displayName' => Str::limit(trim((string) $request->query('name')), 120, '') ?: (StudioSettings::current()->display_name ?? config('app.name')),
                // Nur ein Blob des eigenen Browsers: ein fremder Wert in einem <img> wäre ein Tracking-Pixel.
                'logoUrl' => str_starts_with((string) $request->query('logo'), 'blob:')
                    ? $request->query('logo')
                    : (StudioSettings::current()->logo_path !== null ? '/einstellungen/logo' : null),
                'accentColor' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $request->query('akzent')) ? $request->query('akzent') : StudioSettings::current()->profile()['accent_color'],
                'address' => $request->has('adresse') ? Str::limit(trim((string) $request->query('adresse')), 200, '') : StudioSettings::current()->postalLine(),
            ],
            'portalLegal' => collect(LegalTexts::ALL)
                ->mapWithKeys(fn (string $text) => [$text.'Url' => LegalTexts::url($text)])
                ->all(),
            'portalNav' => null,
        ]);
    }

    /**
     * Das Regal, das die Übersicht unter den Galerien zeigt: die angebotenen
     * Pakete des Studios, sonst zwei Muster, damit der Schalter etwas zeigt.
     *
     * @return list<array<string, mixed>>
     */
    private function sampleShelf(): array
    {
        $packages = Package::query()->portalOffered()->with('items')->orderBy('name')->get();

        if ($packages->isNotEmpty()) {
            return PortalServiceData::collect($packages->values())->toArray();
        }

        return collect([['Mini-Shooting', 'Eine Stunde, 15 Bilder.', 19000], ['Fotobuch', 'Aus euren Lieblingsbildern.', 24900]])
            ->map(fn (array $row, int $i) => [
                'id' => "muster-{$i}", 'name' => $row[0], 'description' => $row[1], 'price' => $row[2],
                'priceMode' => 'exact', 'requiresDate' => false, 'hasImage' => false, 'items' => [],
            ])->all();
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

    /**
     * Wer im Portal ansprechbar ist — eigener Bildschirm, weil das eine
     * Person ist und keine Freigabe: Name, Bild, Telefon, E-Mail und die
     * Antwortzeit stehen auf der Kundenseite zusammen in einer Karte.
     */
    public function contact(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/PortalContact', [
            'settings' => PortalSettingsData::current(),
            // Die Vorschau zeigt, was ohne eigenen Namen dort stünde: die Marke.
            'branding' => StudioSettingsData::current(),
        ]);
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_contact_name' => ['nullable', 'string', 'max:255'],
            // Nur Plus und Ziffern, Leerzeichen zum Gruppieren. Was hier
            // steht, wird auf der Kontaktkarte zu einem tel:-Link.
            'portal_contact_phone' => ['nullable', 'string', 'max:64', new ValidFormat('phone')],
            'portal_contact_email' => ['nullable', 'email', 'max:255'],
            'portal_contact_response_time' => ['nullable', 'string', 'max:120'],
            // Niemals SVG: das Bild geht öffentlich raus, und Markup liefe
            // dann auf unserer Herkunft — dieselbe Grenze wie bei den
            // Beispielbildern.
            'portal_contact_avatar' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ]);

        $actor = auth('staff')->user();

        foreach (collect($validated)->except('portal_contact_avatar') as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        if ($request->hasFile('portal_contact_avatar')) {
            app(UpdatePortalContactAvatar::class)($request->file('portal_contact_avatar'), $actor);
        }

        return back();
    }

    /** Was Kunden im Portal lesen: die vier Rechtstexte, je als Link oder Dokumentvorlage. */
    public function imprintTerms(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/ImprintTerms', [
            'settings' => PortalSettingsData::current(),
            'legalTemplates' => DocumentTemplate::query()
                ->current()
                ->ofType(DocumentTemplateType::Legal)
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentTemplate $template) => [
                    'family' => $template->family_id,
                    'name' => $template->name,
                    'updatedAt' => $template->updated_at?->toDateString(),
                ])
                ->all(),
        ]);
    }

    public function updateImprintTerms(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_legal_impressum_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_datenschutz_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_agb_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_widerruf_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            // Die Vorlage wird als Familie gespeichert, damit das Portal die
            // jeweils aktuelle Fassung zeigt.
            'portal_legal_impressum_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_datenschutz_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_agb_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_widerruf_family' => ['sometimes', 'nullable', 'string', 'size:24', new LegalTemplateFamily],
            'portal_legal_agb_attach' => ['sometimes', 'boolean'],
            'portal_legal_widerruf_attach' => ['sometimes', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return back();
    }

    public function destroyContactAvatar(): RedirectResponse
    {
        $this->authorizeSettings();

        app(UpdatePortalContactAvatar::class)(null, auth('staff')->user());

        return back();
    }

    /** Der eigene Blick auf das Bild; das Portal liefert dieselbe Datei öffentlich aus. */
    public function contactAvatar(): StreamedResponse
    {
        $this->authorizeSettings();

        $path = StudioSettings::current()->portal_contact_avatar_path;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_maintenance_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_maintenance_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_accept_quotes_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_sign_contracts_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_payment_status_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_offers_enabled' => ['sometimes', 'required', 'boolean'],
            // P-09 (#228): everything Sprint 25's seven portal screens do
            // that T-08's four switches never described.
            'portal_messaging_mode' => ['sometimes', 'required', 'in:off,booking,any'],
            'portal_welcome_inquiry' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_welcome_active' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_welcome_done' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return back();
    }
}
