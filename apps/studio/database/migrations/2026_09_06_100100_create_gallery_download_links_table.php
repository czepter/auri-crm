<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_download_links', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // The hash, never the token — the same shape ClientAccess and
            // GalleryEmailVerification already established.
            $table->string('token_hash', 64)->unique();

            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'gallery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_download_links');
    }
};
