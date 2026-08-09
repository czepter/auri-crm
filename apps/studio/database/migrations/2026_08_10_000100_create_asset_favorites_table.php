<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_favorites', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // Scoped to the version, not the Gallery it belongs to — assets
            // are the version's own immutable copy (GalleryVersion's own
            // doc comment), so a favorite naming a key from an old version
            // stays meaningful there even after a republish creates a new
            // one; it just doesn't carry forward to that new version's copy
            // of the same file.
            $table->foreignId('gallery_version_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key');

            // No account behind this — see AssetFavoriteController's own
            // doc comment for what mints it and why.
            $table->string('guest_identifier');

            $table->timestampsTz();

            // One row per guest per asset: the toggle in AssetFavoriteController
            // relies on this to stay race-safe under a doubled-up request
            // rather than something it has to serialize itself.
            $table->unique(['tenant_id', 'gallery_version_id', 'asset_key', 'guest_identifier'], 'asset_favorites_unique_guest_mark');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_favorites');
    }
};
