<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Null until App\Crm\Actions\EraseContactDsgvo runs (X-04). The
            // row itself is never dropped — contract_signatures.contact_id
            // and quotes.accepted_by_contact_id both restrictOnDelete a
            // Contact who has signed or accepted something (see those
            // migrations' own comments), so "erasure" here means every
            // identifying column on this row is scrubbed in place rather
            // than the row disappearing. Once set, nothing unsets it.
            $table->timestampTz('dsgvo_erased_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('dsgvo_erased_at');
        });
    }
};
