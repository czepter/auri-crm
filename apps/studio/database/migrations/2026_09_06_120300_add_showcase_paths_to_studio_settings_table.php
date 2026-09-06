<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q-10: the shop-window pictures the slider's grid is tiled from. Studio
     * level, not quote level — the grid shows quantity, not content, so it
     * has no relation to the single offer it sits on.
     *
     * A json list of paths rather than a table: the same shape logo_path
     * already has, one column beside it, and nothing ever queries an
     * individual picture.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->json('showcase_paths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('showcase_paths');
        });
    }
};
