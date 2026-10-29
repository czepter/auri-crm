<?php

namespace App\Settings\Actions;

use App\Media\Support\ImageVariant;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The logo is stored once (T-02's acceptance criterion) — a re-upload
 * replaces the same tenant's file and removes whatever it replaces, rather
 * than accumulating a second one under a second name. Uses the `local` disk
 * the exact way RenderContractJob stores a rendered contract
 * (Storage::disk('local'), see docs/documents.md), not a second filesystem
 * convention invented for branding alone.
 */
class StoreStudioLogo
{
    public function __invoke(UploadedFile $file, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();
        $old = $row->logo_path;

        // SVG bleibt, wie es ankommt. Alles andere wird auf die Breite gebracht,
        // die ein Beleg braucht: aus einer Kamera oder einem Export kommen
        // Megapixel für ein Zeichen von wenigen Zentimetern.
        if ($file->extension() === 'svg') {
            $path = $file->storeAs("branding/{$row->tenant_id}", 'logo.svg', 'local');
        } else {
            $path = "branding/{$row->tenant_id}/logo.webp";
            Storage::disk('local')->put($path, ImageVariant::resizeAndEncode($file->get(), 800, 'webp'));
        }

        if ($old !== null && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $row->update(['logo_path' => $path]);

        StudioSettings::forgetCurrent();

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['key' => 'logo_path', 'old' => $old, 'new' => $path],
            subject: $row->public_id,
        );
    }
}
