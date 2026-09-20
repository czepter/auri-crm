<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Http\Controllers\Controller;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Enums\QuoteLayout;
use App\Quotes\Support\SampleQuoteData;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Enums\FontPair;
use App\Settings\Models\StudioSettings;
use App\Settings\Rules\LegalTemplateFamily;
use App\Settings\Rules\ValidIban;
use App\Settings\Support\LogoResponse;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The section shell T-00 mocked as screen 14. Studio-Profil — legal entity,
 * brand, bank, T-02's three fieldsets on the one route T-01 scaffolded —
 * with enough save and dirty-state plumbing already in place from that
 * story. Zahlungsdaten & USt is T-03's own pane, Allgemein (timezone,
 * locale, working days) is T-13's; every remaining group renders as a
 * disabled "bald" nav entry: AdminLayout's own sidebar already rules out a
 * link with nothing behind it, and the same call applies one level down.
 */
class StudioSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        $row = StudioSettings::query()->firstOrFail();

        return Inertia::render('Settings/Index', [
            // #234: the four pairs, each with what it is — the screen draws
            // the choice as a preview and not as a sample line.
            'fontPairs' => collect(FontPair::cases())->map(fn (FontPair $pair) => [
                'value' => $pair->value,
                'label' => $pair->label(),
                'description' => $pair->description(),
            ])->all(),
            // The sender only comes from MAIL_FROM_* while the column is
            // empty — an older installation can still carry its own value, and
            // naming the variable would then be a lie. Hence the raw column
            // rather than the resolved value the screen prints.
            'mailSenderFromEnv' => [
                'name' => $row->mail_from_name === null,
                'address' => $row->mail_from_address === null,
            ],
            'settings' => StudioSettingsData::fromResolver($settings),
            // Rechtstexte und Datenschutz-Anfragen stehen im Abschnitt
            // Allgemein: beides sagt etwas über das Studio, nichts über das
            // Portal, in dem es nur auftaucht.
            'portal' => PortalSettingsData::fromResolver($settings),
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

    /**
     * `sometimes` on every rule, because the screen shows the three
     * fieldsets — Rechtliches, Marke, Bankverbindung — as three nav
     * entries with their own save, and each posts only its own fields
     * (see resources/js/admin/pages/Settings/panes/ProfileForm.vue).
     * Onboarding still posts all of them in one step; the loop below
     * writes whatever arrived either way.
     */
    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_form' => ['sometimes', 'nullable', 'string', 'max:100'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            // At least one of the two — a document carrying neither is not
            // invoiceable. required_without on both sides is what makes
            // "at least one" true without a bespoke closure rule.
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50', 'required_without:vat_id'],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:50', 'required_without:tax_number'],
            'register_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'register_court' => ['sometimes', 'nullable', 'string', 'max:255'],
            'representative' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // #234: an enum, never free text — the cases are the pairs this
            // product licensed and self-hosts.
            'font_pair' => ['sometimes', 'nullable', Rule::enum(FontPair::class)],
            // #439: the onboarding's first step asks this beside the identity.
            'client_register' => ['sometimes', 'required', 'in:formal,informal'],
            // SVG ist erlaubt, weil App\Settings\Support\LogoResponse die
            // Datei mit Sandbox-CSP ausliefert; ohne die beiden Kopfzeilen
            // liefe ein <script> darin auf unserer Herkunft.
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            // Optional: a studio that only takes card payments (or none yet
            // during onboarding) has no IBAN to file. What is entered still
            // has to be a real IBAN — the portal's "Überweisen" block hides
            // itself when there is none (portal/pages/Payments/Show.vue).
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_iban' => ['sometimes', 'nullable', 'string', new ValidIban],
            'bank_bic' => ['sometimes', 'nullable', 'string', 'max:11'],
        ]);

        $actor = auth('staff')->user();

        foreach (collect($validated)->except('logo') as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        if ($request->hasFile('logo')) {
            $settings->setLogo($request->file('logo'), $actor);
        }

        return back();
    }

    /**
     * Serves the one stored logo — the same file RenderContractJob's
     * `studio.logoPath` merge field points at — behind the settings screen's
     * own auth gate rather than a public path a filename could be guessed
     * against. A future portal-branding read (T-08's Impressum) reaches the
     * same StudioSettingsResolver::logoPath() this does; nothing here is
     * admin-specific beyond the gate itself.
     */
    public function logo(StudioSettingsResolver $settings): StreamedResponse
    {
        $this->authorizeSettings();

        $path = $settings->logoPath();

        abort_if($path === null, 404);

        return LogoResponse::for($path);
    }

    /**
     * T-03's own save action, separate from update() above: Studio-Profil
     * and Zahlungsdaten & USt are two forms in the mockup, each with its own
     * "Speichern" button, and giving them one shared endpoint would make an
     * unrelated field's validation error block the other form's save.
     *
     * vat_rate arrives already in basis points (see
     * resources/js/admin/lib/money.ts) — the same "never a float" boundary
     * App\Quotes\Enums\DiscountType documents for discount percentages.
     */
    public function updateVat(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'vat_mode' => ['required', 'in:standard,small_business'],
            'vat_rate' => ['required', 'integer', 'min:0', 'max:10000'],
            'vat_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $actor = auth('staff')->user();
        $settings->set('vat_mode', $validated['vat_mode'], $actor);
        $settings->set('vat_rate', $validated['vat_rate'], $actor);
        $settings->set('vat_note', $validated['vat_note'] ?? null, $actor);

        return back();
    }

    /**
     * T-05's own save action, same reasoning as updateVat() above: the
     * Dokumente pane is its own form with its own "Speichern" button.
     *
     * deposit_percentage arrives already in basis points, same boundary
     * vat_rate crosses in updateVat().
     */
    public function updateDocuments(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'quote_validity_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'deposit_percentage' => ['required', 'integer', 'min:0', 'max:10000'],
            'widerruf_included' => ['required', 'boolean'],
            'widerruf_text' => ['nullable', 'string', 'max:5000'],
            'quote_layout' => ['required', Rule::enum(QuoteLayout::class)],
            // Eine Mahnstufe ist eine Frist: sie stand bei Stripe, weil das
            // Geld dort eingeht, gehört aber zu den Fristen daneben.
            'payments_reminders_enabled' => ['sometimes', 'required', 'boolean'],
            // T-12: the shared PDF shell App\Documents\Support\DocumentRenderer
            // wraps a Contract or Quote's resolved body in — this pane's the
            // one place either is set, rather than pasted into each template.
            'document_letterhead_html' => ['nullable', 'string', 'max:20000'],
            'document_footer_html' => ['nullable', 'string', 'max:20000'],
        ]);

        $actor = auth('staff')->user();
        $settings->set('quote_validity_days', $validated['quote_validity_days'], $actor);
        $settings->set('payment_term_days', $validated['payment_term_days'], $actor);
        $settings->set('deposit_percentage', $validated['deposit_percentage'], $actor);
        $settings->set('widerruf_included', $validated['widerruf_included'], $actor);
        $settings->set('widerruf_text', $validated['widerruf_text'] ?? null, $actor);
        $settings->set('quote_layout', $validated['quote_layout'], $actor);
        $settings->set('document_letterhead_html', $validated['document_letterhead_html'] ?? null, $actor);
        $settings->set('document_footer_html', $validated['document_footer_html'] ?? null, $actor);

        if (array_key_exists('payments_reminders_enabled', $validated)) {
            $settings->set('payments_reminders_enabled', $validated['payments_reminders_enabled'], $actor);
        }

        return back();
    }

    /**
     * T-05 follow-up: the Layout radio in the Dokumente pane needs to show
     * what each of the five App\Quotes\Enums\QuoteLayout cases actually
     * renders like — with real markup, not the skeleton bars every other
     * pane's DocumentPreview.vue shows. App\Quotes\Support\SampleQuoteData
     * never touches the database, so this route needs no project or quote.
     */
    public function previewQuoteLayout(Request $request, RenderQuotePdf $render, StudioSettingsResolver $settings): HttpResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'layout' => ['nullable', Rule::enum(QuoteLayout::class)],
        ]);

        $layout = isset($validated['layout']) ? QuoteLayout::from($validated['layout']) : null;
        $sample = SampleQuoteData::build($settings);

        return response($render->html($sample, $layout))->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * T-13's own save action, same reasoning as updateVat() above: Allgemein
     * is its own form with its own "Speichern" button, so an unrelated
     * pane's validation error never blocks this one's save.
     *
     * timezone and locale are nullable — clearing either back to blank
     * returns the tenant to config('app.timezone')/config('app.locale'),
     * per StudioSettingsResolver's fallback contract. working_days is not:
     * an empty selection would leave TaskTemplate::dueFor() nothing to
     * roll onto, so at least one day is required here rather than only
     * guarded defensively in the resolver.
     */
    public function updateGeneral(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', Rule::in(['de', 'de_DE', 'de_AT', 'de_CH'])],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            // Gilt für Portal, Mails und Belege gleichermaßen — deshalb hier
            // und nicht mehr unter Portal.
            'client_register' => ['sometimes', 'required', Rule::in(['formal', 'informal'])],
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
            'dsgvo_request_recipient' => ['sometimes', 'nullable', 'email', 'max:255'],
            'dsgvo_request_deadline_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
        ]);

        $actor = auth('staff')->user();
        $settings->set('timezone', $validated['timezone'] ?? null, $actor);
        $settings->set('locale', $validated['locale'] ?? null, $actor);
        $settings->set('working_days', array_values($validated['working_days']), $actor);

        foreach (Arr::except($validated, ['timezone', 'locale', 'working_days']) as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return back();
    }

    /**
     * The suggestion lists the three "Sonstige" fields at capture offer.
     * Free text stays free text — these only spare the studio retyping the
     * kinds of work it does every week.
     */
    public function updateCapture(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'lead_type_presets' => ['array'],
            'lead_type_presets.*' => ['nullable', 'string', 'max:120'],
            'lead_source_presets' => ['array'],
            'lead_source_presets.*' => ['nullable', 'string', 'max:120'],
            'lead_channel_presets' => ['array'],
            'lead_channel_presets.*' => ['nullable', 'string', 'max:120'],
        ]);

        $actor = auth('staff')->user();

        foreach (['lead_type_presets', 'lead_source_presets', 'lead_channel_presets'] as $key) {
            $settings->set($key, collect($validated[$key] ?? [])
                ->map(fn (?string $value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(), $actor);
        }

        return redirect('/einstellungen?pane=capture');
    }

    /**
     * T-06's own pane and save action, same reasoning as updateVat() above:
     * E-Mail is its own form with its own "Speichern" button.
     *
     * The sender is not a field here: it comes from MAIL_FROM_NAME and
     * MAIL_FROM_ADDRESS, so it cannot drift away from the SMTP identity it
     * has to stay aligned with. mail_sender_reset empties the two columns an
     * older installation may still carry and hands them back to the .env.
     */
    public function updateMail(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'mail_reply_to' => ['nullable', 'string', 'max:255', 'email'],
            'mail_bcc' => ['nullable', 'string', 'max:255', 'email'],
            'mail_signature' => ['nullable', 'string', 'max:1000'],
            'mail_clients_enabled' => ['sometimes', 'boolean'],
            'mail_sender_reset' => ['sometimes', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        if (Arr::pull($validated, 'mail_sender_reset')) {
            $settings->set('mail_from_name', null, $actor);
            $settings->set('mail_from_address', null, $actor);
        }

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return back();
    }
}
