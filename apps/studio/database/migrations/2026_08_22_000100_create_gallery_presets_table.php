<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine benannte Startbelegung für neue Galerien, seit "Vorlage anwenden"
     * auch für bestehende. Kein Fremdschlüssel in beide Richtungen: eine
     * Galerie trägt nie, welche Vorlage sie zuletzt gelesen hat, also
     * erreicht eine geänderte Vorlage bestehende Galerien nie von selbst —
     * nur wenn jemand "Vorlage anwenden" tatsächlich klickt. Ohne diese
     * Trennung würde eine längst ausgelieferte Galerie rückwirkend
     * verfallen, weil jemand später an derselben Vorlage eine Frist
     * korrigiert.
     */
    public function up(): void
    {
        Schema::create('gallery_presets', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('name');

            // C-19's vier Gestaltungsschlüssel, in derselben Form, in der die
            // Galerie sie trägt.
            $table->jsonb('design')->nullable();

            // Drei Zustände, und expires_at kann sie nicht tragen: dort
            // bedeutet null bereits "kein Ablauf", ein zweites null für
            // "erben" wäre nicht mehr lesbar.
            $table->string('expiry_mode')->default('inherit');
            $table->unsignedSmallInteger('expiry_days')->nullable();

            // Dreiwertig: null heißt erben. Eine Vorlage, die zu einem Wert
            // nichts sagt, überschreibt die Ebene darunter nicht.
            $table->boolean('requires_settled_balance')->nullable();
            $table->boolean('downloads_enabled')->nullable();
            $table->boolean('lists_enabled')->nullable();
            $table->boolean('comments_enabled')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_presets');
    }
};
