<?php

namespace App\Settings\Actions;

use App\Media\Support\ImageVariant;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Denselben Weg wie das Logo, mit zwei Unterschieden: niemals SVG — das
 * Bild geht öffentlich über BrandingController raus, und eine SVG-Datei
 * ist Markup — und die Datei wird nicht abgelegt, wie sie ankommt.
 *
 * Aus einer Kamera kommen acht Megapixel für einen Kreis von 54. Abgelegt
 * wird deshalb nur, was die Karte braucht: 320 Pixel breit, als WebP,
 * über dieselbe GD-Strecke, die die Galerie für ihre Varianten fährt.
 * Ohne Datei wird das Bild entfernt.
 */
class UpdatePortalContactAvatar
{
    public function __invoke(?UploadedFile $file, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();
        $old = $row->portal_contact_avatar_path;

        if ($file === null && $old === null) {
            return;
        }

        $path = null;

        if ($file !== null) {
            $path = "branding/{$row->tenant_id}/kontakt.webp";
            Storage::disk('local')->put($path, ImageVariant::resizeAndEncode($file->get(), 320, 'webp'));
        }

        if ($old !== null && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $row->update(['portal_contact_avatar_path' => $path]);

        StudioSettings::forgetCurrent();

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['key' => 'portal_contact_avatar_path', 'old' => $old, 'new' => $path],
            subject: $row->public_id,
        );
    }
}
