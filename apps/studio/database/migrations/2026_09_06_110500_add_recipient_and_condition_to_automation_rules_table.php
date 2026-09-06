<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            // E-14: client, crew or studio. Three values and not two, because
            // StudioAudience::forProject() falls back to everyone where no
            // crew is assigned — right for "Anfrage prüfen", wrong for
            // "Ausrüstung packen".
            $table->string('recipient_type')->default('client');
            // E-15: the one condition checked before sending. A rule that
            // fires without looking at the state reminds the couple who paid
            // three weeks ago, and one false alarm devalues the channel.
            $table->string('condition')->nullable();
        });

        // E-16: the date a contract has to be signed by. Nothing wrote it
        // before; the anchor is what asks for it.
        Schema::table('contracts', function (Blueprint $table) {
            $table->date('sign_deadline_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn(['recipient_type', 'condition']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('sign_deadline_on');
        });
    }
};
