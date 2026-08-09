<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_installments', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('payment_plan_id')->constrained()->cascadeOnDelete();

            // Minor units (cents) — never a float. The sum-matches-plan-total
            // invariant lives in the next migration's trigger, not here: a
            // plain CHECK constraint can only see this one row.
            $table->unsignedInteger('amount');

            $table->date('due_on');

            // scheduled | paid | invoiced — App\Payments\Enums\PaymentInstallmentStatus.
            // Nothing in M-03 ever writes 'invoiced'; the column and the value
            // exist now so M-04's real Stripe invoicing has somewhere to land
            // without a later migration widening this column's domain.
            $table->string('status')->default('scheduled');

            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['payment_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_installments');
    }
};
