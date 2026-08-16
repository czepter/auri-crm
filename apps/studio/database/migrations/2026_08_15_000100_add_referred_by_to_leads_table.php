<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sent this client. LeadSource::Referral records that a client was
 * referred and nothing about by whom, so the Übersicht cannot say "hat
 * Steffens & Falk empfohlen" and the studio cannot thank anyone.
 *
 * Nullable on purpose: a referral whose referrer nobody wrote down is still a
 * referral, and a required field would push whoever takes the call into
 * inventing one. nullOnDelete rather than cascade: EraseContactDsgvo never
 * hard-deletes the Contact row it erases (it scrubs the identifying columns
 * in place), so this only fires if a contact is ever actually removed from
 * the table — and even then, losing the lead that names a referrer is a
 * worse outcome than the lead simply forgetting who referred it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('referred_by_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_contact_id');
        });
    }
};
