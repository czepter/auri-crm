<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// I-01: the api surface's second kind of credential, alongside G-01's guest
// tokens minted on a Contact. An ApiToken is its own tokenable (Laravel\
// Sanctum\HasApiTokens, tokenable_type=App\Integration\Models\ApiToken in
// personal_access_tokens) rather than reusing Contact — a machine caller is
// not a guest of one project, so it earns its own row rather than borrowing
// a shape built for someone else. See App\Integration\Models\ApiToken.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('name');

            // The S-06 role policies, expressed as token scopes — see the
            // model's own docblock. Never a vocabulary of its own: every
            // value here is a valid App\Enums\Capability case.
            $table->jsonb('scopes')->default('[]');

            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->timestampTz('revoked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
