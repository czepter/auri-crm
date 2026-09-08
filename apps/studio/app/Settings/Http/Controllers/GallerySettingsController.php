<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
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

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'gallery_default_expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'gallery_default_requires_settled_balance' => ['required', 'boolean'],
            'gallery_default_downloads_enabled' => ['required', 'boolean'],
            'gallery_default_lists_enabled' => ['required', 'boolean'],
            'gallery_default_comments_enabled' => ['required', 'boolean'],
            // Ganz oder gar nicht, wie auf der Vorlage: die vier Schluessel
            // stehen zusammen oder das Feld fehlt.
            'gallery_default_design' => ['nullable', 'array'],
            'gallery_default_design.cover' => ['required_with:gallery_default_design', Rule::in(['full', 'split', 'plain'])],
            'gallery_default_design.type' => ['required_with:gallery_default_design', 'string', 'max:32'],
            'gallery_default_design.colour' => ['required_with:gallery_default_design', Rule::in(['light', 'dark', 'warm'])],
            'gallery_default_design.grid' => ['required_with:gallery_default_design', Rule::in(['wide', 'dense', 'single'])],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return redirect('/galerien');
    }
}
