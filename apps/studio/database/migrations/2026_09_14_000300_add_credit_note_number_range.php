<?php

use App\Numbering\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

/**
 * #409. The fourth range, seeded here for the reason 2026_08_09_002300 gives
 * for the first three: AllocateDocumentNumber calls firstOrFail() on the row,
 * so every tenant — and every RefreshDatabase run — needs it configured
 * before the first Gutschrift, not after the first 500.
 *
 * GS for Gutschrift, the same German convention as AN, V and RE.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now('UTC');

        DB::table('number_ranges')->insertOrIgnore([
            'tenant_id' => config('tenancy.tenant_id'),
            'public_id' => (string) new Cuid2,
            'document_type' => DocumentType::CreditNote->value,
            'prefix' => 'GS',
            'reset_rule' => 'yearly',
            'starting_counter' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('number_ranges')->where('document_type', DocumentType::CreditNote->value)->delete();
    }
};
