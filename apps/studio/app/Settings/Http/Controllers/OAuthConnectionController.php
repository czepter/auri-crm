<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Integration\Models\OAuthClient;
use App\Integration\Support\OAuthClients;
use App\Support\AuditLogger;
use App\Support\PersonalAccessToken;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OAuthConnectionController extends Controller
{
    use AuthorizesStaffSettings;

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $client = OAuthClient::create(['client_id' => Str::random(32), ...$this->validated($request)]);

        AuditLogger::record('mcp_oauth.client_created', auth('staff')->user(), subject: $client->public_id);

        return redirect('/einstellungen/api-token');
    }

    public function update(Request $request, OAuthClient $oauthClient): RedirectResponse
    {
        $this->authorizeSettings();

        $oauthClient->update($this->validated($request));

        AuditLogger::record('mcp_oauth.client_updated', auth('staff')->user(), subject: $oauthClient->public_id);

        return redirect('/einstellungen/api-token');
    }

    public function destroy(OAuthClient $oauthClient): RedirectResponse
    {
        $this->authorizeSettings();

        PersonalAccessToken::query()->where('oauth_client_id', $oauthClient->client_id)->delete();
        $oauthClient->delete();

        AuditLogger::record('mcp_oauth.client_deleted', auth('staff')->user(), subject: $oauthClient->public_id);

        return redirect('/einstellungen/api-token');
    }

    /** @return array{client_name: string, redirect_uris: list<string>} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail) {
                if (! OAuthClients::isRegisterableRedirectUri($value)) {
                    $fail(__('validation.url'));
                }
            }],
        ]);

        return ['client_name' => $validated['client_name'], 'redirect_uris' => array_values($validated['redirect_uris'])];
    }
}
