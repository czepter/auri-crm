<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Vier Rechtstexte, zwei Quellen: eine fremde Adresse oder eine Vorlage
     * aus dem eigenen Editor. Impressum und Widerrufsbelehrung kommen neu
     * dazu; AGB und Datenschutz haben ihre URL-Spalte schon.
     *
     * Die Vorlage steht als family_id, nicht als template_id: eine neue
     * Fassung eines Rechtstexts bekommt eine neue Zeile, und das Portal soll
     * die jeweils aktuelle zeigen, nicht die von damals.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('portal_legal_impressum_url')->nullable();
            $table->string('portal_legal_widerruf_url')->nullable();

            // char(24) wie document_templates.family_id — kein uuid, die ids
            // dort sind kurze Zeichenketten.
            foreach (['impressum', 'datenschutz', 'agb', 'widerruf'] as $text) {
                $table->char("portal_legal_{$text}_family", 24)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn([
                'portal_legal_impressum_url',
                'portal_legal_widerruf_url',
                'portal_legal_impressum_family',
                'portal_legal_datenschutz_family',
                'portal_legal_agb_family',
                'portal_legal_widerruf_family',
            ]);
        });
    }
};
