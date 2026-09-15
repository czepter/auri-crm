<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #415: when the invoice went out and when the client first opened it —
 * Invoice Ninja's `sent_date` / `viewed_date` on the invitation, here on the
 * installment because one rate is one invoice and one recipient list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'viewed_at']);
        });
    }
};
