<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-07: how an installment was actually settled — never how it WAS GOING to
 * be settled. `settled_via` stays null until App\Payments\Actions\
 * MarkInstallmentPaid writes it, alongside the date, method and reference a
 * staff member (or, once M-04/M-05 land, a Stripe webhook) actually gave.
 * Nothing upstream of that action ever chooses a value in advance — see
 * App\Payments\Enums\SettledVia.
 *
 * `overdue_notified_at` is this story's own idempotency guard for the
 * scheduled overdue scan (App\Payments\Actions\DetectOverdueInstallments):
 * without it, every daily run would refire InstallmentOverdue for the same
 * still-unpaid installment. Cleared by nothing — once notified, it stays
 * notified, the same one-way guard SignContract's own idempotency check uses
 * for `contract_signatures`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->string('settled_via')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestampTz('overdue_notified_at')->nullable();
        });

        DB::statement("alter table payment_installments add constraint payment_installments_settled_via_check check (settled_via in ('stripe', 'transfer', 'manual'))");
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn(['settled_via', 'paid_on', 'payment_reference', 'overdue_notified_at']);
        });
    }
};
