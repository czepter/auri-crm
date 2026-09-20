<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Settings\Support\LogoResponse;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-08's portal-side read of the one stored logo — the same file
 * StudioSettingsController::logo() serves on the admin surface and
 * RenderContractJob's `studio.logoPath` merge field points at
 * (StudioSettingsResolver::logoPath()'s own doc comment already names this
 * as the future read). Public: a brand mark is not a secret, and this route
 * has to resolve on the sign-in screen, before any contact session exists.
 */
class BrandingController extends Controller
{
    public function logo(StudioSettingsResolver $settings): StreamedResponse
    {
        $path = $settings->logoPath();

        abort_if($path === null, 404);

        return LogoResponse::for($path);
    }

    /**
     * Q-10: one shop-window picture from the slider's grid. Public for the
     * same reason the logo is — these are pictures the photographer uploaded
     * to show, the way one puts them on a website, and the grid renders
     * before any quote is accepted. Indexed rather than named, so no stored
     * filename ever reaches a URL.
     */
    public function showcase(int $index, StudioSettingsResolver $settings): StreamedResponse
    {
        $path = $settings->showcasePaths()[$index] ?? null;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
