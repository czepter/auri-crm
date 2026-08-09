<?php

use App\Numbering\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises Q-05's quote_number_counters into the counter table every
 * DocumentType allocates through — the table itself, its rows and its
 * concurrency guarantee (transaction + unique index) are preserved exactly,
 * per T-04's issue; this only widens what the index keys on so a second
 * document type can share the mechanism without colliding with the first.
 *
 * Every row this table already holds is a quote row — `default('quote')`
 * backfills them for free, no separate UPDATE needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_number_counters', function (Blueprint $table) {
            $table->string('document_type')->default(DocumentType::Quote->value)->after('tenant_id');
        });

        Schema::table('quote_number_counters', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'year', 'counter']);
            $table->unique(['tenant_id', 'document_type', 'year', 'counter']);
        });
    }

    public function down(): void
    {
        Schema::table('quote_number_counters', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'document_type', 'year', 'counter']);
            $table->unique(['tenant_id', 'year', 'counter']);
        });

        Schema::table('quote_number_counters', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
