<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #417: the one word before the due date, stamped once. No counter like
 * `overdue_reminders_sent` next door — there is a single rung here, so the
 * timestamp is both the guard and the record of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->timestamp('before_due_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn('before_due_reminder_sent_at');
        });
    }
};
