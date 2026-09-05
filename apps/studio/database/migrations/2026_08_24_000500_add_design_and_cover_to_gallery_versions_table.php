<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Doc-Kommentar der Versionstabelle behauptet Unveränderlichkeit
     * seit ihrem ersten Tag. Sie galt nur für Titel und Bilder: Gestaltung
     * und Titelbild las die Auslieferung live aus galleries, ein
     * veröffentlichter Stand änderte sich also rückwirkend. Beides gehört in
     * den Schnappschuss.
     */
    public function up(): void
    {
        Schema::table('gallery_versions', function (Blueprint $table) {
            $table->jsonb('design')->nullable()->after('assets');
            $table->string('cover_key')->nullable()->after('design');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_versions', function (Blueprint $table) {
            $table->dropColumn(['design', 'cover_key']);
        });
    }
};
