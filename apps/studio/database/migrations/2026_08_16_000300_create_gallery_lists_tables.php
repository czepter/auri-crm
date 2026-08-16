<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-06 (#225): the client's own selections.
     *
     * Hung off the gallery, never off a GalleryVersion. A version is frozen
     * at publish() and a re-publish makes a new one, so a list attached to a
     * version would be orphaned the moment the studio fixed a caption —
     * exactly the thing a couple who spent an evening picking forty pictures
     * must never experience.
     *
     * asset_key is a plain string for the same reason: assets live inside a
     * version's JSON, there is no assets table to point a foreign key at, and
     * a key that survives a re-publish is precisely what should carry the
     * list across it. A key that does not survive simply stops matching, and
     * the list is short by one rather than gone.
     *
     * name is nullable: a guest's heart and a client's unnamed favorites are
     * the same thing, and giving the unnamed list a special table would mean
     * two implementations of one idea.
     */
    public function up(): void
    {
        Schema::create('gallery_lists', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'gallery_id', 'contact_id']);
        });

        Schema::create('gallery_list_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_list_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();

            // One picture is either on a list or it is not; adding it twice
            // is the same click twice.
            $table->unique(['gallery_list_id', 'asset_key']);
        });

        Schema::table('galleries', function (Blueprint $table) {
            // P-06's "Aufrufe". DownloadEvent has only ever counted
            // downloads, so nothing told a couple whether the link they
            // shared was opened at all. Counted once per visitor session
            // rather than per request — see GalleryPasswordController.
            $table->unsignedInteger('view_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', fn (Blueprint $table) => $table->dropColumn('view_count'));
        Schema::dropIfExists('gallery_list_items');
        Schema::dropIfExists('gallery_lists');
    }
};
