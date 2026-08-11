<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // No Contact here, deliberately: the email gate proves a guest
            // controls an inbox, not that the inbox belongs to a known
            // client — anyone with the gallery link may request one.
            $table->string('email');

            // The hash, never the token — the same shape ClientAccess
            // already established.
            $table->string('token_hash', 64)->unique();

            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'gallery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_email_verifications');
    }
};
