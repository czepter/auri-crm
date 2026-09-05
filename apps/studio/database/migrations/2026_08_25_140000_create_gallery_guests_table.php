<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Gast in einer Galerie — der Platz, den es dafür bisher nicht gab.
     *
     * Bis hierher war ein Gast eine zufällige UUID im localStorage seines
     * Browsers: Gerätewechsel löschte jede Markierung, jeder Kommentar hieß
     * „Gast", und ein fertiges ZIP hatte niemanden, dem es Bescheid sagen
     * konnte.
     *
     * Je Galerie, nicht je Studio: eine Bestätigung, die für die Galerie
     * einer fremden Hochzeit mitgilt, wäre eine Einwilligung, die niemand
     * gegeben hat.
     */
    public function up(): void
    {
        Schema::create('gallery_guests', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            $table->string('name');

            // Der Hash der Adresse. asset_favorites.guest_identifier und
            // asset_comments.guest_identifier tragen genau diesen Wert, und
            // dieselbe Adresse ergibt ihn auf jedem Gerät neu.
            $table->string('identifier', 64);

            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'email']);
            // Jede Herz- und Kommentarabfrage sucht hierüber.
            $table->index(['tenant_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_guests');
    }
};
