<?php

namespace App\Integration\Console\Commands;

use App\Integration\Models\ApiToken;
use Illuminate\Console\Command;

/**
 * I-01's revoke path — see CreateApiTokenCommand's own docblock for why this
 * is a command rather than an admin screen action.
 */
class RevokeApiTokenCommand extends Command
{
    protected $signature = 'api-token:revoke {public_id : The ApiToken\'s public_id}';

    protected $description = 'Revokes an ApiToken, deleting every physical token it ever minted.';

    public function handle(): int
    {
        $token = ApiToken::findByPublicId($this->argument('public_id'));

        if (! $token) {
            $this->error('No ApiToken found for that public_id.');

            return self::FAILURE;
        }

        $token->revoke();

        $this->info("Revoked ApiToken \"{$token->name}\" ({$token->public_id}).");

        return self::SUCCESS;
    }
}
