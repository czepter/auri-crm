<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Die Kontaktzeile war drei Felder im Portal-Formular. Wer im Portal
 * dasteht, ist aber ein Mensch mit Namen und Gesicht, nicht die Marke —
 * PortalPerson malte bisher die Initialen des Studionamens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('portal_contact_name')->nullable();
            $table->string('portal_contact_avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['portal_contact_name', 'portal_contact_avatar_path']);
        });
    }
};
