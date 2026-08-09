<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sanctum's own migration, with the two columns every table in this app
// carries from its first migration (G-01) added on top: tenant_id so a guest
// token is scoped the same way any other row is, and public_id so a token
// row is never referred to by its sequential id if a future screen ever
// needs to list or revoke one. Neither is read by Sanctum itself — the
// tokenable relation already scopes a token to its owner's tenant — see
// App\Support\PersonalAccessToken for the model that keeps them in sync.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
