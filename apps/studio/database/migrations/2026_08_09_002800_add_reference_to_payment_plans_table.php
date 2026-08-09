<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-07's Verwendungszweck: the document number CreatePaymentPlan allocates
 * (T-04's AllocateDocumentNumber, DocumentType::Invoice) the moment a plan
 * is created — a plan already requires a signed contract, so unlike a Q-06
 * quote send this is never a draft that could burn a number for nothing.
 * One number per plan, printed on every installment's transfer instructions
 * (the portal payment screen) because they are all payments against the
 * same document. Nullable: a plan created before this story's own action
 * runs (fixtures, factories) simply has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->string('reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
