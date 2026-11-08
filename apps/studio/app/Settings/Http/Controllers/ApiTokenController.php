<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Integration\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/ApiTokens', [
            'tokens' => ApiToken::query()->whereNull('revoked_at')->withMax('tokens', 'last_used_at')->latest()->get()
                ->map(fn (ApiToken $token) => [
                    'id' => $token->public_id,
                    'name' => $token->name,
                    'scopes' => $token->scopes,
                    'createdAt' => $token->created_at->toIso8601String(),
                    'lastUsedAt' => $token->tokens_max_last_used_at ? Carbon::parse($token->tokens_max_last_used_at)->toIso8601String() : null,
                ]),
            'capabilities' => array_map(
                fn (Capability $c) => ['value' => $c->value, 'label' => $c->label()],
                Capability::cases(),
            ),
            'mcpUrl' => config('services.mcp.url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::enum(Capability::class)],
        ]);

        $token = ApiToken::create($validated);

        // Sanctum stores only the hash; this flash is the one time the value is readable.
        return redirect('/einstellungen/api-token')
            ->with('secret', $token->createToken($token->name)->plainTextToken);
    }

    public function destroy(ApiToken $apiToken): RedirectResponse
    {
        $this->authorizeSettings();

        $apiToken->revoke();

        return redirect('/einstellungen/api-token');
    }
}
