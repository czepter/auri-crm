<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zwei Wege zur selben Bestätigung, ein Datensatz dahinter.
     *
     * Der Link trägt weiter das lange Token. Daneben stehen sechs Ziffern
     * zum Abtippen — die Mail auf dem Telefon zu lesen und am Rechner zu
     * sitzen ist bei einer Hochzeitsgalerie der Normalfall, und wer dort
     * klickt, landet im falschen Browser.
     *
     * attempts bremst das Durchprobieren: sechs Ziffern sind eine Million
     * Möglichkeiten, aber ohne Zähler reichen ein paar Minuten.
     */
    public function up(): void
    {
        Schema::table('gallery_email_verifications', function (Blueprint $table) {
            $table->string('code_hash', 64)->nullable()->after('token_hash');
            $table->unsignedSmallInteger('attempts')->default(0)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_email_verifications', function (Blueprint $table) {
            $table->dropColumn(['code_hash', 'attempts']);
        });
    }
};
