<?php

namespace App\Integration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Portal\Support\LegalTexts;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mirrors PortalSettingsController::updateImprintTerms, links only. */
class LegalTextController extends Controller
{
    public function update(Request $request, UpdateStudioSetting $update): JsonResponse
    {
        $validated = $request->validate([
            'impressum' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'datenschutz' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'agb' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'widerruf' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
        ]);

        $principal = $request->user('api');

        foreach ($validated as $text => $url) {
            $update("portal_legal_{$text}_url", $url, $principal instanceof User ? $principal : null);
        }

        return response()->json([
            'data' => collect(LegalTexts::ALL)->mapWithKeys(fn (string $text) => [$text => LegalTexts::externalUrl($text)])->all(),
        ]);
    }
}
