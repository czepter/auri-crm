<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\GallerySettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The gallery defaults, split off the Portal pane: they are read by the
 * ingest screen and by ContactController's per-contact overrides, neither of
 * which the portal switches touch.
 */
class GallerySettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Galleries', [
            'settings' => GallerySettingsData::fromResolver($settings),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'gallery_default_expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'gallery_default_requires_settled_balance' => ['required', 'boolean'],
            'gallery_default_downloads_enabled' => ['required', 'boolean'],
            'gallery_default_lists_enabled' => ['required', 'boolean'],
            'gallery_default_comments_enabled' => ['required', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return redirect('/einstellungen/galerien');
    }
}
