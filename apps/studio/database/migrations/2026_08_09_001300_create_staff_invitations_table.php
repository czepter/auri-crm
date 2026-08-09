<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Its own table rather than a half-built row in users: a user exists
        // when someone can sign in, and an invited person cannot until they
        // accept. Keeping the pending state here means no query anywhere has
        // to remember to exclude accounts that are not really accounts yet.
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('email');
            $table->string('name');
            $table->string('role');

            // The hash, never the token. The raw value exists only in the link
            // that leaves the server, exactly as a password reset works — a
            // leaked database must not hand over live invitations.
            $table->string('token_hash', 64)->unique();

            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignId('invited_by_id')->constrained('users');
            $table->timestampsTz();

            // One live invitation per address. Re-inviting replaces rather than
            // accumulating, so a revoked link cannot be resurrected by an older
            // one still sitting in the table.
            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('password_reset_tokens');
    }
};
