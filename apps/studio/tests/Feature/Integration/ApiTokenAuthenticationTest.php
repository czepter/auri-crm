<?php

use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\AuditLog;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);

    // A route behind exactly the middleware every real api-surface route
    // carries — proving the mechanism EnsureAudience now enforces for any
    // ApiToken caller, without committing routes/api.php to a business
    // endpoint this infrastructure story does not itself need. Future
    // stories attach real resources the same way, with `scope:<capability>`
    // added where one is required.
    Route::middleware('audience:api')->get('/api/_test/probe', fn () => response()->json(['ok' => true]));

    Route::middleware(['audience:api', 'scope:'.Capability::ManageContacts->value])
        ->get('/api/_test/contacts-scope', fn () => response()->json(['ok' => true]));
});

it('authenticates an ApiToken minted machine credential', function () {
    $token = ApiToken::factory()->create();
    $plainTextToken = $token->createToken($token->name)->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/_test/probe')
        ->assertOk();
});

it('refuses a revoked ApiToken even carrying a still-existing physical token', function () {
    $token = ApiToken::factory()->revoked()->create();
    $plainTextToken = $token->createToken($token->name)->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/_test/probe')
        ->assertUnauthorized();
});

it('refuses a fully revoked ApiToken, whose minted tokens are gone', function () {
    $token = ApiToken::factory()->create();
    $plainTextToken = $token->createToken($token->name)->plainTextToken;
    $token->revoke();

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/_test/probe')
        ->assertUnauthorized();
});

it('leaves an audit row for every authenticated call an ApiToken makes', function () {
    $token = ApiToken::factory()->create();
    $plainTextToken = $token->createToken($token->name)->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")->getJson('/api/_test/probe')->assertOk();
    $this->withHeader('Authorization', "Bearer {$plainTextToken}")->getJson('/api/_test/probe')->assertOk();

    expect(AuditLog::where('event', 'api_token.call')->where('subject', $token->public_id)->count())->toBe(2);
});

it('rate limits calls per token, at the token\'s own configured limit', function () {
    $token = ApiToken::factory()->create(['rate_limit_per_minute' => 1]);
    $plainTextToken = $token->createToken($token->name)->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")->getJson('/api/_test/probe')->assertOk();

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/_test/probe')
        ->assertStatus(429);

    expect(AuditLog::where('event', 'api_token.rate_limited')->where('subject', $token->public_id)->count())->toBe(1);
});

it('rate limits each token independently', function () {
    $throttled = ApiToken::factory()->create(['rate_limit_per_minute' => 1]);
    $roomy = ApiToken::factory()->create(['rate_limit_per_minute' => 60]);

    $throttledToken = $throttled->createToken('t')->plainTextToken;
    $roomyToken = $roomy->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$throttledToken}")->getJson('/api/_test/probe')->assertOk();
    $this->withHeader('Authorization', "Bearer {$throttledToken}")->getJson('/api/_test/probe')->assertStatus(429);

    // The 'api' guard caches whichever principal it resolved for the first
    // request made within this test method (RequestGuard::$user, cached
    // once and reused for the AuthManager's own cached guard instance) — a
    // real request never sees this because each one gets a fresh process.
    // Forgetting it here is what makes the switch to $roomyToken's bearer
    // header actually take effect on this second simulated request.
    Auth::forgetGuards();

    $this->withHeader('Authorization', "Bearer {$roomyToken}")->getJson('/api/_test/probe')->assertOk();
});

it('lets a token reach a scope-gated route only when it holds that scope', function () {
    $allowed = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $denied = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $allowedToken = $allowed->createToken('t')->plainTextToken;
    $deniedToken = $denied->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$allowedToken}")
        ->getJson('/api/_test/contacts-scope')
        ->assertOk();

    Auth::forgetGuards(); // see the rate-limit test above for why

    $this->withHeader('Authorization', "Bearer {$deniedToken}")
        ->getJson('/api/_test/contacts-scope')
        ->assertForbidden();
});

it('never lets a guest token minted on a contact reach a scope-gated route', function () {
    $contact = Contact::factory()->create();
    $guestToken = $contact->createToken('guest')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$guestToken}")
        ->getJson('/api/_test/contacts-scope')
        ->assertForbidden();
});
