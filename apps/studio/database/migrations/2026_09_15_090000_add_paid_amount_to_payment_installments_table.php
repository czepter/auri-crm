<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #414: what has actually arrived on this installment, in minor units. A row
 * settled before this migration ran was all-or-nothing, so backfilling the
 * full amount for `paid` is the only reading of it that keeps
 * PaymentPlanData::$openTotal — now derived from this column — unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->unsignedInteger('paid_amount')->default(0);
        });

        DB::table('payment_installments')
            ->where('status', 'paid')
            ->update(['paid_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
