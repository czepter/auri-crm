<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Q-07's three columns: App\Quotes\Actions\AcceptQuote is the only writer,
 * all three landing together in the one `sent -> accepted` transition
 * Quote::booted()'s guard allows alongside `status` itself.
 *
 * accepted_by_contact_id restrictOnDelete for the same reason contact_id
 * does on contract_signatures: a contact who has accepted something is not
 * one this app can drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->timestampTz('accepted_at')->nullable()->after('number');
            $table->string('accepted_ip', 45)->nullable()->after('accepted_at');
            $table->foreignId('accepted_by_contact_id')->nullable()->after('accepted_ip')
                ->constrained('contacts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by_contact_id');
            $table->dropColumn(['accepted_at', 'accepted_ip']);
        });
    }
};
