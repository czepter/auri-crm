<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine Leistung, die das Studio genau diesem Kunden vorschlägt.
     *
     * Das Regal — packages.portal_offered — sieht jeder Bestandskunde gleich.
     * Hier steht die Handarbeit: eine Leistung, zu der ein Satz gehört, den
     * jemand über diese Buchungen geschrieben hat. Das ist der Unterschied
     * zwischen Katalog und Aftersales, und deshalb eine eigene Zeile statt
     * einer weiteren Spalte am Paket.
     *
     * Kontaktbezogen, nicht projektbezogen: vorgeschlagen wird einem Menschen,
     * und ein Paar sind zwei Kontakte mit je eigenem Portalzugang — wer
     * vorschlägt, entscheidet, wen er anspricht.
     *
     * Ein Paket höchstens einmal je Kontakt. Zweimal dasselbe vorzuschlagen
     * ist keine zweite Aussage, sondern eine geänderte.
     */
    public function up(): void
    {
        Schema::create('service_suggestions', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            // Das Paket verschwindet, der Vorschlag auch: er hat ohne die
            // Leistung nichts mehr zu sagen.
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            // Der Satz. Nullable, weil ein Vorschlag ohne Begründung auch einer
            // ist — er steht dann beim Kunden im Regal statt darüber.
            $table->text('note')->nullable();

            // Wer ihn geschrieben hat. Nullable und nullOnDelete, weil ein
            // ausgeschiedener Mitarbeiter den Vorschlag nicht mitnimmt.
            $table->foreignId('suggested_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'contact_id', 'package_id']);
            $table->index(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_suggestions');
    }
};
