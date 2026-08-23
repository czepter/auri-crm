<?php

namespace App\Gallery\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Models\GalleryPreset;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eigener Screen neben den Studio-Standardwerten, nicht ein zweiter Abschnitt
 * auf ihnen: eine Vorlage ist eine benannte Sache, von der es mehrere gibt,
 * und die Rail führt sie wie Aufgaben- und Fragebogenvorlagen.
 */
class GalleryPresetController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/GalleryPresets', [
            'presets' => GalleryPresetData::collect(GalleryPreset::query()->orderBy('name')->get()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $preset = GalleryPreset::create($this->validated($request));

        AuditLogger::record(
            event: 'gallery_preset.created',
            actor: auth('staff')->user(),
            context: ['name' => $preset->name],
            subject: $preset->public_id,
        );

        return redirect('/einstellungen/galerie-vorlagen');
    }

    public function update(Request $request, GalleryPreset $galleryPreset): RedirectResponse
    {
        $this->authorizeSettings();

        $galleryPreset->update($this->validated($request));

        return redirect('/einstellungen/galerie-vorlagen');
    }

    public function destroy(GalleryPreset $galleryPreset): RedirectResponse
    {
        $this->authorizeSettings();

        AuditLogger::record(
            event: 'gallery_preset.deleted',
            actor: auth('staff')->user(),
            context: ['name' => $galleryPreset->name],
            subject: $galleryPreset->public_id,
        );

        $galleryPreset->delete();

        return redirect('/einstellungen/galerie-vorlagen');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'expiry_mode' => ['required', Rule::in(GalleryPreset::MODES)],
            'expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:expiry_mode,days'],
            'requires_settled_balance' => ['nullable', 'boolean'],
            'downloads_enabled' => ['nullable', 'boolean'],
            'lists_enabled' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
            'design' => ['nullable', 'array'],
            'design.cover' => ['nullable', 'string', 'max:32'],
            'design.type' => ['nullable', 'string', 'max:32'],
            'design.colour' => ['nullable', 'string', 'max:32'],
            'design.grid' => ['nullable', 'string', 'max:32'],
        ]);
    }
}
