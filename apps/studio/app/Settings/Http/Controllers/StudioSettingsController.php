<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Concerns\FramesPreview;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\MailDefaults;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Notifications\Support\MailPreview;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\StripeEvent;
use App\Payments\Support\SampleInvoiceData;
use App\Portal\Notifications\PortalAccessInvitation;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Enums\QuoteLayout;
use App\Quotes\Support\SampleQuoteData;
use App\Settings\Actions\AdaptClientRegister;
use App\Settings\Actions\StoreStudioLogo;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Data\PaymentSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Enums\FontPair;
use App\Settings\Rules\ValidFormat;
use App\Settings\Rules\ValidIban;
use App\Settings\Support\LogoResponse;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The section shell T-00 mocked as screen 14. Studio-Profil — legal entity,
 * brand, bank, T-02's three fieldsets on the one route T-01 scaffolded —
 * with enough save and dirty-state plumbing already in place from that
 * story. Zahlungsdaten & USt is T-03's own pane, and Allgemein carries
 * brand, legal entity, tax and timezone on one page; every remaining group renders as a
 * disabled "bald" nav entry: AdminLayout's own sidebar already rules out a
 * link with nothing behind it, and the same call applies one level down.
 */
class StudioSettingsController extends Controller
{
    use AuthorizesStaffSettings;
    use FramesPreview;

    public function edit(string $pane): Response
    {
        $this->authorizeSettings();

        $row = StudioSettings::query()->firstOrFail();

        return Inertia::render('Settings/Index', [
            'pane' => $pane,
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
            'mailSenderFromEnv' => LiveUpdate::live([
                'name' => $row->mail_from_name === null,
                'address' => $row->mail_from_address === null,
            ]),
            'settings' => LiveUpdate::live(StudioSettingsData::current()),
            'stripe' => LiveUpdate::live($pane === 'payment' ? PaymentSettingsData::current() : null),
            'stripeStatus' => LiveUpdate::live($pane === 'payment' ? [
                'lastWebhookAt' => StripeEvent::query()->max('created_at'),
                'lastPaidOn' => PaymentInstallment::query()->where('settled_via', SettledVia::Stripe)->max('paid_on'),
            ] : null),
            'mailTemplates' => LiveUpdate::live($pane === 'email'
                ? DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Mail)->orderBy('name')->get()
                    ->map(fn (DocumentTemplate $template) => ['id' => $template->family_id, 'name' => $template->name])->all()
                : []),
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
    public function update(Request $request, AdaptClientRegister $adaptRegister): RedirectResponse
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
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50', 'required_without:vat_id', new ValidFormat('tax_number')],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:50', 'required_without:tax_number', new ValidFormat('vat_id')],
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
            'adapt_existing' => ['sometimes', 'boolean', 'prohibited_unless:client_register,formal,informal'],
            // Blank returns the tenant to config('app.timezone'), per
            // StudioSettings::current()'s fallback contract.
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            // SVG ist erlaubt, weil App\Settings\Support\LogoResponse die
            // Datei mit Sandbox-CSP ausliefert; ohne die beiden Kopfzeilen
            // liefe ein <script> darin auf unserer Herkunft.
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:10240'],
            // Optional: a studio that only takes card payments (or none yet
            // during onboarding) has no IBAN to file. What is entered still
            // has to be a real IBAN — the portal's "Überweisen" block hides
            // itself when there is none (portal/pages/Payments/Show.vue).
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_iban' => ['sometimes', 'nullable', 'string', new ValidIban],
            'bank_bic' => ['sometimes', 'nullable', 'string', 'max:11', new ValidFormat('bic')],
        ]);

        $actor = auth('staff')->user();
        $registerChanged = isset($validated['client_register']) && $validated['client_register'] !== StudioSettings::current()->client_register;

        foreach (collect($validated)->except('logo', 'adapt_existing') as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        if ($request->hasFile('logo')) {
            app(StoreStudioLogo::class)($request->file('logo'), $actor);
        }

        if ($registerChanged && ($validated['adapt_existing'] ?? false)) {
            $counts = $adaptRegister($validated['client_register']);

            AuditLogger::record('studio.register_adapted', $actor, ['to' => $validated['client_register'], ...$counts]);

            return back()->with('status', __('settings.register_adapted', $counts));
        }

        return back();
    }

    /**
     * Die Kundenmail, wie sie ankommt: dieselbe Vorlage mit Signatur und
     * Anschrift im Fuß. Die Signatur kommt getippt statt gespeichert, damit
     * die Vorschau mitläuft.
     */
    public function previewMail(Request $request): HttpResponse|RedirectResponse
    {
        $this->authorizeSettings();

        if ($page = $this->framed($request, '/einstellungen/email')) {
            return $page;
        }

        // The frame as typed, before it is saved.
        $unsaved = array_filter([
            'mail_signature' => $request->has('signatur') ? Str::limit((string) $request->query('signatur'), 1000, '') : null,
            'mail_header_name' => $request->has('kopf') ? $request->boolean('kopf') : null,
            'mail_footer_legal' => $request->has('recht') ? $request->boolean('recht') : null,
            'mail_footer_extra' => $request->has('zeile') ? Str::limit((string) $request->query('zeile'), 255, '') : null,
        ], fn ($value) => $value !== null);

        $html = StudioSettings::withUnsaved($unsaved, fn () => MailPreview::html(
            new PortalAccessInvitation(config('portal.url').'/zugang/…', '', 'Hallo Maxi Musterfrau,'),
            (object) [],
        ));

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function registerPreview(Request $request, AdaptClientRegister $adaptRegister): JsonResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate(['ziel' => ['required', 'in:formal,informal']]);

        return response()->json($adaptRegister($validated['ziel'], dryRun: true));
    }

    /**
     * Serves the one stored logo — the same file RenderContractJob's
     * `studio.logoPath` merge field points at — behind the settings screen's
     * own auth gate rather than a public path a filename could be guessed
     * against. A future portal-branding read (T-08's Impressum) reaches the
     * same StudioSettings' logo_path this does; nothing here is
     * admin-specific beyond the gate itself.
     */
    public function logo(): StreamedResponse
    {
        $this->authorizeSettings();

        $path = StudioSettings::current()->logo_path;

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
    public function updateVat(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'vat_mode' => ['required', 'in:standard,small_business'],
            'vat_rate' => ['required', 'integer', 'min:0', 'max:10000'],
            'vat_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $actor = auth('staff')->user();
        app(UpdateStudioSetting::class)('vat_mode', $validated['vat_mode'], $actor);
        app(UpdateStudioSetting::class)('vat_rate', $validated['vat_rate'], $actor);
        app(UpdateStudioSetting::class)('vat_note', $validated['vat_note'] ?? null, $actor);

        return back();
    }

    /**
     * Zahlung and Belege are two pages with their own "Speichern", so each
     * validates only its own fields (`sometimes`): a page sends what it shows.
     *
     * deposit_percentage arrives already in basis points, same boundary
     * vat_rate crosses in updateVat().
     */
    public function updatePayment(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'payment_term_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:3650'],
            'deposit_percentage' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
            // Eine Mahnstufe ist eine Frist: sie stand bei Stripe, weil das
            // Geld dort eingeht, gehört aber zu den Fristen daneben.
            'payments_reminders_enabled' => ['sometimes', 'required', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return back();
    }

    public function updateReceipts(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'quote_validity_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:3650'],
            'document_layout' => ['sometimes', 'required', Rule::enum(QuoteLayout::class)],
            // T-12: the shared PDF shell App\Documents\Support\DocumentRenderer
            // wraps a Contract or Quote's resolved body in.
            'document_letterhead_html' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'document_footer_html' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return back();
    }

    /**
     * T-05 follow-up: the Layout radio on the Belege page needs to show
     * what each of the five App\Quotes\Enums\QuoteLayout cases actually
     * renders like — with real markup, not the skeleton bars every other
     * pane's DocumentPreview.vue shows. App\Quotes\Support\SampleQuoteData
     * never touches the database, so this route needs no project or quote.
     */
    public function previewQuoteLayout(Request $request, RenderQuotePdf $render): HttpResponse|RedirectResponse
    {
        $this->authorizeSettings();

        if ($page = $this->framed($request, '/einstellungen/layout')) {
            return $page;
        }

        $validated = $request->validate([
            'layout' => ['nullable', Rule::enum(QuoteLayout::class)],
        ]);

        $layout = isset($validated['layout']) ? QuoteLayout::from($validated['layout']) : null;
        $sample = SampleQuoteData::build();

        return response($render->html($sample, $layout))->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** The Zahlung pane's sheet: an Anzahlungsrechnung carrying the bank data and terms as typed, unsaved. */
    public function previewInvoice(Request $request, RenderInvoicePdf $render): HttpResponse|RedirectResponse
    {
        $this->authorizeSettings();

        if ($page = $this->framed($request, '/einstellungen/zahlung')) {
            return $page;
        }

        $validated = $request->validate([
            'holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:64'],
            'bic' => ['nullable', 'string', 'max:32'],
            'days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'layout' => ['nullable', Rule::enum(QuoteLayout::class)],
        ]);

        $settings = StudioSettings::current();
        $days = (int) ($validated['days'] ?? $settings->payment_term_days);
        $deposit = (float) ($validated['deposit'] ?? $settings->deposit_percentage / 100);

        $html = StudioSettings::withUnsaved([
            'bank_account_holder' => $validated['holder'] ?? $settings->bank_account_holder,
            'bank_iban' => preg_replace('/\s+/', '', $validated['iban'] ?? $settings->bank_iban ?? ''),
            'bank_bic' => $validated['bic'] ?? $settings->bank_bic,
        ], fn () => $render->forInvoice(
            SampleInvoiceData::build($days, $deposit),
            isset($validated['layout']) ? QuoteLayout::from($validated['layout']) : null,
        ));

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
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
    public function updateMail(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'mail_reply_to' => ['nullable', 'string', 'max:255', 'email'],
            'mail_bcc' => ['nullable', 'string', 'max:255', 'email'],
            'mail_signature' => ['nullable', 'string', 'max:1000'],
            'mail_clients_enabled' => ['sometimes', 'boolean'],
            'mail_header_name' => ['sometimes', 'boolean'],
            'mail_footer_legal' => ['sometimes', 'boolean'],
            'mail_footer_extra' => ['nullable', 'string', 'max:255'],
            'mail_defaults' => ['sometimes', 'array'],
            'mail_defaults.*' => ['nullable', 'string', Rule::exists('document_templates', 'family_id')->where('type', 'mail')],
            'mail_sender_reset' => ['sometimes', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        if (Arr::pull($validated, 'mail_sender_reset')) {
            app(UpdateStudioSetting::class)('mail_from_name', null, $actor);
            app(UpdateStudioSetting::class)('mail_from_address', null, $actor);
        }

        if (isset($validated['mail_defaults'])) {
            $validated['mail_defaults'] = array_filter(Arr::only($validated['mail_defaults'], MailDefaults::OCCASIONS));
        }

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return back();
    }
}
