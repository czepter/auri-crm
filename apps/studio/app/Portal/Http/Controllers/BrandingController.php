<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Settings\Support\LogoResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-08's portal-side read of the one stored logo — the same file
 * StudioSettingsController::logo() serves on the admin surface and
 * RenderContractJob's `studio.logoPath` merge field points at
 * (StudioSettings' logo_path's own doc comment already names this
 * as the future read). Public: a brand mark is not a secret, and this route
 * has to resolve on the sign-in screen, before any contact session exists.
 */
class BrandingController extends Controller
{
    public function logo(): StreamedResponse
    {
        $path = StudioSettings::current()->logo_path;

        abort_if($path === null, 404);

        return LogoResponse::for($path);
    }

    /**
     * Das Gesicht auf der Kontaktkarte. Öffentlich aus demselben Grund wie
     * das Logo: es steht auf jedem Kundenbildschirm, und SVG kommt hier gar
     * nicht erst an — siehe App\Settings\Actions\UpdatePortalContactAvatar.
     */
    public function contactAvatar(): StreamedResponse
    {
        $path = StudioSettings::current()->portal_contact_avatar_path;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
