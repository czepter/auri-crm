<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #454: payment and gallery are separate. A gallery never locks over an open
 * balance, so neither the gallery, its preset nor the studio default carries
 * the gate any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', fn (Blueprint $table) => $table->dropColumn('requires_settled_balance'));
        Schema::table('gallery_presets', fn (Blueprint $table) => $table->dropColumn('requires_settled_balance'));
        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('gallery_default_requires_settled_balance'));
    }

    public function down(): void
    {
        Schema::table('galleries', fn (Blueprint $table) => $table->boolean('requires_settled_balance')->default(false));
        Schema::table('gallery_presets', fn (Blueprint $table) => $table->boolean('requires_settled_balance')->nullable());
        Schema::table('studio_settings', fn (Blueprint $table) => $table->boolean('gallery_default_requires_settled_balance')->default(false));
    }
};
