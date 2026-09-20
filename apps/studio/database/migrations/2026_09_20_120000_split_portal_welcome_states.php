<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * "Ohne Buchung" was two different people: the stranger who has only asked,
 * and the client whose last booking is delivered. The second never had a
 * room, so portal_welcome_text stood in as a fallback for both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->renameColumn('portal_welcome_many', 'portal_welcome_active');
            $table->text('portal_welcome_done')->nullable();
        });

        DB::table('studio_settings')->update([
            'portal_welcome_inquiry' => DB::raw('coalesce(portal_welcome_inquiry, portal_welcome_text)'),
            'portal_welcome_active' => DB::raw('coalesce(portal_welcome_active, portal_welcome_text)'),
            'portal_welcome_done' => DB::raw('portal_welcome_text'),
        ]);

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('portal_welcome_text');
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->text('portal_welcome_text')->nullable();
        });

        DB::table('studio_settings')->update([
            'portal_welcome_text' => DB::raw('portal_welcome_active'),
        ]);

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('portal_welcome_done');
            $table->renameColumn('portal_welcome_active', 'portal_welcome_many');
        });
    }
};
