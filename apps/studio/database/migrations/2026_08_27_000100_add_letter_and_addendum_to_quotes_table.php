<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Anschreiben steht auf dem Angebot, nicht in der Vorlage: die Vorlage
 * trägt, was jedes Angebot gleich sagt, diese zwei Felder das, was nur für
 * dieses gilt. Merge-Felder darin lösen beim Rendern auf
 * (App\Quotes\Actions\RenderQuotePdf).
 *
 * is_addendum unterscheidet den Nachtrag von der Revision, die
 * predecessor_id schon trägt: eine Revision ersetzt ihren Vorgänger
 * (App\Quotes\Actions\ReviseQuote setzt ihn auf superseded), ein Nachtrag
 * tritt neben das beauftragte Angebot, das angenommen bleibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->text('intro')->nullable();
            $table->text('outro')->nullable();
            $table->boolean('is_addendum')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['intro', 'outro', 'is_addendum']);
        });
    }
};
