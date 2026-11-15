<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Gallery\Support\GalleryDesign;
use App\Http\Controllers\Controller;
use App\Settings\Actions\UpdateStudioSetting;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Die Galerie-Vorgaben. Seit G-14 haben sie keinen eigenen Screen mehr: sie
 * werden auf /galerien bedient, wie Stufen und Projekttypen auf /auftraege.
 * Was bleibt, ist die Stelle, an die der Raum schreibt.
 */
class GallerySettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'gallery_default_expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'gallery_default_download_rule' => ['sometimes', 'required', Rule::in(['open', 'after_payment', 'off'])],
            'gallery_default_lists_enabled' => ['sometimes', 'required', 'boolean'],
            'gallery_default_comments_enabled' => ['sometimes', 'required', 'boolean'],
            // Ganz oder gar nicht, wie auf der Vorlage: die sechs Schluessel
            // stehen zusammen oder das Feld fehlt.
            'gallery_default_design' => ['nullable', 'array'],
            ...GalleryDesign::rules('gallery_default_design', 'required_with:gallery_default_design'),
            'gallery_home_mode' => ['sometimes', 'required', Rule::in(['none', 'redirect', 'overview'])],
            'gallery_home_redirect_url' => [
                'exclude_unless:gallery_home_mode,redirect', 'required', 'url:http,https', 'max:2000',
                function (string $attribute, mixed $value, Closure $fail) {
                    $host = strtolower((string) parse_url((string) config('services.gallery.url'), PHP_URL_HOST));

                    if (strtolower((string) parse_url((string) $value, PHP_URL_HOST)) === $host) {
                        $fail(__('gallery.home_redirect_self', ['host' => $host]));
                    }
                },
            ],
            'gallery_home_noindex' => ['sometimes', 'required', 'boolean'],
        ], [
            'gallery_home_redirect_url.required' => __('gallery.home_redirect_required'),
            'gallery_home_redirect_url.url' => __('gallery.home_redirect_scheme'),
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return redirect('/galerien');
    }
}
