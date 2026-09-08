<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * G-15/G-16: die Gestaltung bekommt eine Studio-Vorgabe, und eine Vorlage
     * darf der Startpunkt sein.
     *
     * gallery_default_design traegt dieselben vier Schluessel wie
     * galleries.design und gallery_presets.design — null heisst, dass das
     * Studio nichts gesagt hat, und die Galerie faellt auf die eingebauten
     * Werte zurueck.
     *
     * Genau eine Vorlage ist Standard: der partielle Unique-Index laesst
     * beliebig viele Zeilen mit false zu und je Tenant nur eine mit true. Die
     * Regel gehoert in die Datenbank, weil zwei Startpunkte keine Anzeige
     * verwirren, sondern die naechste Galerie zufaellig geboren werden lassen.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->jsonb('gallery_default_design')->nullable();
        });

        Schema::table('gallery_presets', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });

        DB::statement(
            'CREATE UNIQUE INDEX gallery_presets_one_default_per_tenant
             ON gallery_presets (tenant_id) WHERE is_default'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gallery_presets_one_default_per_tenant');

        Schema::table('gallery_presets', fn (Blueprint $table) => $table->dropColumn('is_default'));
        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('gallery_default_design'));
    }
};
