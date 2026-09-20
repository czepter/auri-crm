<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q-10 zog die Schaufensterbilder auf Studio-Ebene, weil das Raster
     * Menge zeigt und nicht Inhalt. Es sitzt aber am Regler eines einzelnen
     * Angebots, neben images_per_hour, und die Bilder, die eine Hochzeit
     * illustrieren, sind nicht die, die ein Produktshooting illustrieren.
     * Also dorthin, wo die Stundenwerte schon stehen: ans Paket als
     * Startsatz, ans Angebot als das, was der Kunde sieht.
     *
     * Die alten Pfade werden nicht verteilt — welches Angebot sie gemeint
     * haben, weiss niemand. Die Dateien bleiben auf der Disk liegen.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->json('showcase_paths')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->json('showcase_paths')->nullable();
        });

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('showcase_paths');
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->json('showcase_paths')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('showcase_paths');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('showcase_paths');
        });
    }
};
