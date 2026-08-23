<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die zweite Ablage fällt. Sie wurde nur von ImportLegacyGallery und dem
     * E2eSeeder beschrieben, während der Upload Asset-Zeilen anlegte — eine
     * hochgeladene Galerie zeigte deshalb nie ein Bild. Kein Rückfüllen:
     * bestehende Entwurfslisten gehen verloren, veröffentlichte Versionen
     * tragen ihren Schnappschuss selbst.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('assets');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->json('assets')->default('[]');
        });
    }
};
