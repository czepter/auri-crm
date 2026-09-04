<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R39/R42: one row per address that asked to hear about a gallery still
     * in pre-release. No Contact — the same reasoning
     * GalleryEmailVerification already applies: a guest proves nothing but
     * an inbox here, and anyone holding the address may sign it up.
     *
     * "Same address" is decided at write time (the action lowercases before
     * insert, App\Gallery\Models\GalleryPrereleaseSignup::normalizeEmail())
     * rather than with a case-insensitive index, so the unique constraint
     * below can stay a plain column tuple like every other one in this
     * schema instead of a Postgres-specific expression index.
     */
    public function up(): void
    {
        Schema::create('gallery_prerelease_signups', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestampTz('notified_at')->nullable();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_prerelease_signups');
    }
};
