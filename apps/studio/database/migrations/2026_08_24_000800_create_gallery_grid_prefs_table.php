<?php

use App\Gallery\Models\GalleryGridPref;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same shape recent_views (C-03, #237) already used for this kind of
     * preference. A row per (user, set); the foreign key takes the orphan
     * with it when the set is deleted, instead of a json column on the user
     * that nobody sweeps.
     */
    public function up(): void
    {
        Schema::create('gallery_grid_prefs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_set_id')->constrained('gallery_sets')->cascadeOnDelete();

            $table->string('density')->default(GalleryGridPref::DEFAULT_DENSITY);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id', 'gallery_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_grid_prefs');
    }
};
