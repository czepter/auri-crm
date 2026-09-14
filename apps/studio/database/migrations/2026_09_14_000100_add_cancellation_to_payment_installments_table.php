<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #408. The number, the date and the path stay on the row — a Storno keeps
 * the document readable and the sequence gapless (GoBD Rz. 93), so nothing
 * here clears what IssueInvoice wrote.
 *
 * The kind is a CHECK rather than a Postgres enum type: a single-row fact,
 * the same reasoning quotes_net_check in 2026_08_09_002000 already rests on,
 * and a widened domain later is one ALTER instead of a type rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_kind')->nullable();
            $table->text('cancellation_reason')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payment_installments
                ADD CONSTRAINT payment_installments_cancellation_kind_check
                CHECK (cancellation_kind IS NULL OR cancellation_kind IN ('correction', 'void'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_installments DROP CONSTRAINT payment_installments_cancellation_kind_check');

        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_kind', 'cancellation_reason']);
        });
    }
};
