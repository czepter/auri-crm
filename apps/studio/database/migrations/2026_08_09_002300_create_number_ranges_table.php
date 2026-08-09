<?php

use App\Numbering\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_ranges', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('document_type');
            $table->string('prefix');
            $table->string('reset_rule');

            // Settable while the range has issued nothing for its current
            // period (App\Settings\Models\NumberRange::hasIssued()) — how
            // X-01's future historical import starts a studio's live
            // numbering at the count it already reached, rather than at 1
            // under a number a client has already been sent.
            $table->unsignedInteger('starting_counter')->default(1);

            $table->timestampsTz();

            // One row per tenant per document type — never a second range
            // for the same type competing for the same numbers.
            $table->unique(['tenant_id', 'document_type']);
        });

        // Seeded here, the same reasoning studio_settings (T-01) gave for
        // seeding its own one row at migration time: every tenant needs all
        // three ranges configured before AllocateDocumentNumber can be
        // called for any of them, so a fresh install (and every test, via
        // RefreshDatabase) gets its ranges the moment this table exists.
        // Prefixes follow the German convention the mockups already use for
        // a quote (AN — Angebot); V (Vertrag) and RE (Rechnung) are the same
        // convention applied to the two document types M-01 and M-04 add.
        $tenantId = config('tenancy.tenant_id');
        $now = now('UTC');

        DB::table('number_ranges')->insert([
            [
                'tenant_id' => $tenantId,
                'public_id' => (string) new Cuid2,
                'document_type' => DocumentType::Quote->value,
                'prefix' => 'AN',
                'reset_rule' => 'yearly',
                'starting_counter' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'public_id' => (string) new Cuid2,
                'document_type' => DocumentType::Contract->value,
                'prefix' => 'V',
                'reset_rule' => 'yearly',
                'starting_counter' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'public_id' => (string) new Cuid2,
                'document_type' => DocumentType::Invoice->value,
                'prefix' => 'RE',
                'reset_rule' => 'yearly',
                'starting_counter' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('number_ranges');
    }
};
