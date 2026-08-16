<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Portal\Actions\ConsumeClientAccess;
use App\Portal\Actions\RequestClientAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MagicLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Login');
    }

    /**
     * Always the same answer. Whether the address is known, whether a link was
     * sent, whether a limit was hit — none of it reaches the response, because
     * any of it would turn this form into a way of asking the studio who its
     * clients are.
     */
    public function store(Request $request, RequestClientAccess $issue): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $issue($validated['email'], $request->ip());

        return back()->with('status', __('portal.link_sent'));
    }

    public function consume(Request $request, string $token, ConsumeClientAccess $consume): RedirectResponse
    {
        $contact = $consume($token, $request->ip());

        if ($contact === null) {
            return redirect('/anmelden')->with('status', __('portal.link_dead'));
        }

        Auth::guard('portal')->login($contact);

        // A fresh session id, so a token that was ever visible in a URL bar,
        // a proxy log or a referrer cannot be replayed against the session it
        // created.
        $request->session()->regenerate();

        // P-05 (#224): what "sign out on every device" measures a session
        // against — see EnsureAudience::sessionWasEnded().
        $request->session()->put('portal_session_started_at', now()->timestamp);

        return redirect('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/anmelden');
    }
}
