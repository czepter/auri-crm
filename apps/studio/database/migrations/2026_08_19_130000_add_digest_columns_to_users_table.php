<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-11's summary. Two columns rather than a table: a rhythm is one value
     * per person, and what goes into the mail is read from in_app_notifications
     * — an event set to "Zusammenfassung" is an in-app row whose mail was
     * suppressed, so the digest needs no log of its own.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null is off. daily / weekly / monthly otherwise.
            $table->string('digest_frequency')->nullable();
            $table->timestampTz('digest_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['digest_frequency', 'digest_sent_at']));
    }
};
