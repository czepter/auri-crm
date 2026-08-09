<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Rules\ValidIban;
use App\Settings\Support\BankDetailsRequirement;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The section shell T-00 mocked as screen 14. Studio-Profil — legal entity,
 * brand, bank, T-02's three fieldsets on the one route T-01 scaffolded —
 * with enough save and dirty-state plumbing already in place from that
 * story. Zahlungsdaten & USt and every group below it render as disabled
 * "bald" nav entries: AdminLayout's own sidebar already rules out a link
 * with nothing behind it, and the same call applies one level down.
 */
class StudioSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Index', [
            'settings' => $this->toData($settings),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $bankRule = BankDetailsRequirement::isRequired() ? 'required' : 'nullable';

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            // At least one of the two — a document carrying neither is not
            // invoiceable. required_without on both sides is what makes
            // "at least one" true without a bespoke closure rule.
            'tax_number' => ['nullable', 'string', 'max:50', 'required_without:vat_id'],
            'vat_id' => ['nullable', 'string', 'max:50', 'required_without:tax_number'],
            'register_number' => ['nullable', 'string', 'max:100'],
            'register_court' => ['nullable', 'string', 'max:255'],
            'representative' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // SVG deliberately excluded: served inline from this app's own
            // origin (logo(), below), an uploaded SVG can carry a <script>
            // and execute as this app rather than as an image.
            'logo' => ['nullable', 'file', 'mimes:png', 'max:2048'],
            'bank_account_holder' => [$bankRule, 'string', 'max:255'],
            'bank_iban' => [$bankRule, 'string', new ValidIban],
            'bank_bic' => [$bankRule, 'string', 'max:11'],
        ]);

        $actor = auth('staff')->user();

        foreach (collect($validated)->except('logo') as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        if ($request->hasFile('logo')) {
            $settings->setLogo($request->file('logo'), $actor);
        }

        return redirect('/einstellungen');
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

        return Storage::disk('local')->response($path);
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

        return redirect('/einstellungen');
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
        ]);

        $actor = auth('staff')->user();
        $settings->set('quote_validity_days', $validated['quote_validity_days'], $actor);
        $settings->set('payment_term_days', $validated['payment_term_days'], $actor);
        $settings->set('deposit_percentage', $validated['deposit_percentage'], $actor);
        $settings->set('widerruf_included', $validated['widerruf_included'], $actor);
        $settings->set('widerruf_text', $validated['widerruf_text'] ?? null, $actor);

        return redirect('/einstellungen');
    }

    private function toData(StudioSettingsResolver $settings): StudioSettingsData
    {
        $attrs = $settings->attributes();

        return new StudioSettingsData(
            id: $settings->publicId(),
            displayName: $attrs['display_name'],
            legalName: $attrs['legal_name'],
            legalForm: $attrs['legal_form'],
            street: $attrs['street'],
            postcode: $attrs['postcode'],
            city: $attrs['city'],
            country: $attrs['country'],
            taxNumber: $attrs['tax_number'],
            vatId: $attrs['vat_id'],
            registerNumber: $attrs['register_number'],
            registerCourt: $attrs['register_court'],
            representative: $attrs['representative'],
            website: $attrs['website'],
            accentColor: $attrs['accent_color'],
            logoUrl: $settings->logoPath() !== null ? route('settings.logo') : null,
            bankAccountHolder: $attrs['bank_account_holder'],
            bankIban: $attrs['bank_iban'],
            bankBic: $attrs['bank_bic'],
            vatMode: $settings->vatMode(),
            vatRate: $settings->vatRate(),
            vatNote: $settings->vatNote(),
            quoteValidityDays: $settings->quoteValidityDays(),
            paymentTermDays: $settings->paymentTermDays(),
            depositPercentage: $settings->depositPercentage(),
            widerrufIncluded: $settings->widerrufIncluded(),
            widerrufText: $settings->widerrufText(),
        );
    }
}
