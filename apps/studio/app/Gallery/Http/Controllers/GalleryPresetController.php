<?php

namespace App\Gallery\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Gallery\Data\GalleryPresetData;
use App\Gallery\Models\Gallery;
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

    /**
     * Die Einstellungen einer Galerie als benannte Vorlage sichern — der Weg,
     * den man tatsaechlich geht: erst eine Galerie einrichten, bis sie sitzt,
     * dann festhalten, womit die naechste anfangen soll.
     *
     * Zwei Tore, weil zwei Dinge beruehrt werden: die Galerie, aus der gelesen
     * wird, und die Studio-Einstellungen, in die geschrieben wird.
     */
    public function storeFromGallery(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorizeSettings();
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $preset = GalleryPreset::create([
            'name' => $validated['name'],
            'design' => $gallery->design,
            'requires_settled_balance' => $gallery->requires_settled_balance,
            'downloads_enabled' => $gallery->downloads_enabled,
            'lists_enabled' => $gallery->lists_enabled,
            'comments_enabled' => $gallery->comments_enabled,
            ...$this->expiryOf($gallery),
        ]);

        AuditLogger::record(
            event: 'gallery_preset.created',
            actor: auth('staff')->user(),
            context: ['name' => $preset->name, 'from_gallery' => $gallery->public_id],
            subject: $preset->public_id,
        );

        return redirect('/einstellungen/galerie-vorlagen');
    }

    /**
     * Die Galerie traegt ein Datum, die Vorlage eine Spanne. Uebersetzt wird
     * der Abstand zwischen Anlage und Ablauf — das ist, was diese Galerie
     * mitbekommen hat, und nicht die Restlaufzeit von heute aus.
     *
     * @return array{expiry_mode: string, expiry_days: int|null}
     */
    private function expiryOf(Gallery $gallery): array
    {
        if ($gallery->expires_at === null) {
            return ['expiry_mode' => 'never', 'expiry_days' => null];
        }

        return [
            'expiry_mode' => 'days',
            'expiry_days' => max(1, $gallery->created_at->diffInDays($gallery->expires_at)),
        ];
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
