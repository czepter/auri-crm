<?php

namespace App\Integration\Console\Commands;

use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use Illuminate\Console\Command;

/**
 * I-01's minting path. No admin screen exists yet for this — the story's own
 * acceptance is the credential, the scope and the rate limit working, not a
 * UI to manage them — so a machine token is issued here, once, and the
 * plaintext secret is shown exactly this one time (Sanctum never stores it).
 */
class CreateApiTokenCommand extends Command
{
    protected $signature = 'api-token:create
        {name : A label for who or what holds this token}
        {--scope=* : One or more App\Enums\Capability values this token may reach}
        {--rate-limit=60 : Requests per minute this token is allowed}';

    protected $description = 'Mints a new ApiToken machine credential on the api surface and prints its plaintext secret once.';

    public function handle(): int
    {
        $scopes = $this->option('scope');

        foreach ($scopes as $scope) {
            if (Capability::tryFrom($scope) === null) {
                $known = implode(', ', array_map(fn (Capability $c) => $c->value, Capability::cases()));
                $this->error("Unknown capability scope \"{$scope}\". Known scopes: {$known}.");

                return self::FAILURE;
            }
        }

        $token = ApiToken::create([
            'name' => $this->argument('name'),
            'scopes' => $scopes,
            'rate_limit_per_minute' => (int) $this->option('rate-limit'),
        ]);

        $plainTextToken = $token->createToken($token->name)->plainTextToken;

        $this->info("Created ApiToken \"{$token->name}\" ({$token->public_id}).");
        $this->line("Token (shown once): {$plainTextToken}");

        return self::SUCCESS;
    }
}
