<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('portal_legal_agb_attach')->default(false);
            $table->boolean('portal_legal_widerruf_attach')->default(false);
            $table->dropColumn(['widerruf_included', 'widerruf_text']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['portal_legal_agb_attach', 'portal_legal_widerruf_attach']);
            $table->boolean('widerruf_included')->default(true);
            $table->text('widerruf_text')->nullable();
        });
    }
};
