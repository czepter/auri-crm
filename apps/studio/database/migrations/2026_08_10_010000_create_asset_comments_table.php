<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G-12: the lightbox's comment thread, per asset. Same guest-identity shape
 * as asset_favorites (G-09) — no account behind guest_identifier, see
 * AssetCommentController's own doc comment — and the same version-scoping
 * reasoning: an asset_key is only ever meaningful against the GalleryVersion
 * that minted it (GalleryVersion's own doc comment), so a comment lives
 * there too rather than on the mutable Gallery draft.
 *
 * Unlike asset_favorites there is no unique index here: a guest can leave
 * more than one comment on the same asset, unlike a favorite mark which is
 * either on or off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_comments', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_version_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key');
            $table->string('guest_identifier');
            $table->text('body');

            $table->timestampsTz();

            $table->index(['tenant_id', 'gallery_version_id', 'asset_key'], 'asset_comments_by_asset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_comments');
    }
};
