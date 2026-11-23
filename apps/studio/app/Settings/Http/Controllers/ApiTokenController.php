<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Integration\Models\ApiToken;
use App\Integration\Models\OAuthClient;
use App\Support\PersonalAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'connections' => OAuthClient::query()->latest()->get()
                ->map(fn (OAuthClient $client) => [
                    'id' => $client->public_id,
                    'clientId' => $client->client_id,
                    'name' => $client->client_name,
                    'redirectUris' => $client->redirect_uris,
                    'lastUsedAt' => ($lastUsed = PersonalAccessToken::query()->where('oauth_client_id', $client->client_id)->max('last_used_at'))
                        ? Carbon::parse($lastUsed)->toIso8601String() : null,
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

        $token = ApiToken::create($this->validated($request));

        // Sanctum stores only the hash; this flash is the one time the value is readable.
        return redirect('/einstellungen/api-token')
            ->with('secret', $token->createToken($token->name)->plainTextToken);
    }

    public function update(Request $request, ApiToken $apiToken): RedirectResponse
    {
        $this->authorizeSettings();
        abort_if($apiToken->isRevoked(), 404);

        $apiToken->update($this->validated($request));

        return redirect('/einstellungen/api-token');
    }

    public function destroy(ApiToken $apiToken): RedirectResponse
    {
        $this->authorizeSettings();

        $apiToken->revoke();

        return redirect('/einstellungen/api-token');
    }

    /** @return array{name: string, scopes: list<string>} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::enum(Capability::class)],
        ]);
    }
}
