<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die Studio-Vorgabe für „Weitere Leistungen" im Portal, übersteuerbar je
     * Kontakt über contacts.portal_overrides. Eigener Schalter und nicht an
     * portal_accept_quotes_enabled gehängt: ein Kunde mitten in einer
     * Reklamation soll nichts angeboten bekommen, darf aber weiter annehmen,
     * was er schon bekommen hat.
     *
     * Aus als Voreinstellung, wie jede andere Portalfreigabe: dass ein Studio
     * seinen Katalog gebaut hat, ist nicht seine Zustimmung, ihn jedem Kunden
     * hinzustellen.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('portal_offers_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('portal_offers_enabled');
        });
    }
};
