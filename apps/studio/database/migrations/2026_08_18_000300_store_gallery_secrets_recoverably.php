<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-17 (#253): the view password and the download code are stored
     * recoverably and shown in the admin in clear.
     *
     * Both values are handed to guests anyway — by mail, by WhatsApp, over
     * the phone. A studio that cannot look one up sets a new one on every
     * enquiry and locks out everybody holding the old. The hash protected
     * nothing here that is not already shared.
     *
     * What it costs, unvarnished: a database dump now contains every gallery
     * password and PIN. Encryption with APP_KEY defends against the stolen
     * dump, not against an attacker who can run the application. That is the
     * price of showing the value, and for something sent by messenger it is
     * a fair one. Staff login passwords and client_accesses.token_hash are
     * not covered by this and stay hashes — they are never shown.
     *
     * The old hash columns stay for now. Dropping them would turn every
     * gallery that already has a gate into an open one at deploy time, since
     * no hash can be decrypted into the new column — a privacy regression
     * nobody asked for. Gallery::check*() reads the encrypted value first and
     * falls back to the hash, and every rotate clears the legacy column. They
     * can be dropped once no row has one.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->text('view_password')->nullable();
            $table->text('download_pin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['view_password', 'download_pin']);
        });
    }
};
