<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welche Leistung ein Bestandskunde im Portal angefragt hat.
 *
 * Der Name des Vorgangs trägt sie bisher als Text — damit lässt sich zählen,
 * was ein Paket im Portal einbringt, nur solange niemand den Vorgang
 * umbenennt. Die Übersicht und das Paket selbst beantworten genau diese Frage,
 * also steht sie als Fremdschlüssel da.
 *
 * Das ist keine lebende Verbindung im Sinne von Package: kopiert wird von hier
 * nichts, der Lead liest keine Position nach. Ein Paket zu ändern bewegt
 * weiterhin nichts, was daraus entstanden ist — es bleibt nur die Auskunft,
 * worauf der Kunde geklickt hat.
 *
 * Nullable, weil die beiden anderen Wege in den Trichter — Erfassung von Hand
 * und Anfrageformular — von keinem Paket ausgehen. nullOnDelete: ein
 * gelöschtes Paket darf die Anfrage nicht mitnehmen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });
    }
};
