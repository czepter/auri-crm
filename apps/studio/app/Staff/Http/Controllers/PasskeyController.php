<?php

namespace App\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Staff\Models\Passkey;
use App\Staff\Support\WebAuthn;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PasskeyController extends Controller
{
    /** Asked before the browser dialog, so a wrong password leaves no orphan on the device. */
    public function options(Request $request, WebAuthn $webauthn): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:staff']]);

        $user = $request->user('staff');
        $options = $webauthn->creationOptions($user);

        WebAuthn::stash('passkey.creation', ['user_id' => $user->id, 'options' => $options]);

        return JsonResponse::fromJsonString($options);
    }

    public function store(Request $request, WebAuthn $webauthn): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'credential' => ['required', 'string'],
        ]);

        $user = $request->user('staff');
        $challenge = WebAuthn::pull('passkey.creation');

        $passkey = $challenge === null || $challenge['user_id'] !== $user->id
            ? null
            : $webauthn->register($user, $data['name'], $challenge['options'], $data['credential']);

        if ($passkey === null) {
            throw ValidationException::withMessages(['credential' => __('auth.passkey_failed')]);
        }

        AuditLogger::record('staff.passkey.registered', $user);

        return back();
    }

    public function destroy(Request $request, Passkey $passkey): RedirectResponse
    {
        $user = $request->user('staff');

        abort_unless($passkey->user_id === $user->id, 404);

        $request->validate(['current_password' => ['required', 'current_password:staff']]);

        $passkey->delete();

        AuditLogger::record('staff.passkey.deleted', $user);

        return back();
    }
}
