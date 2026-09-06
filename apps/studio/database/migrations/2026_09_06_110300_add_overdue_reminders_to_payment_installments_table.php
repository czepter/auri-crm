<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            // T-22: overdue_notified_at keeps the domain event firing once;
            // this counts how often the studio has been told since.
            $table->unsignedSmallInteger('overdue_reminders_sent')->default(0);
            // T-25: the client's own ladder, deliberately one rung behind.
            $table->unsignedSmallInteger('client_reminders_sent')->default(0);
            $table->string('overdue_reason')->nullable();
        });

        // T-25: the single plan the studio switches off after a phone call,
        // under the studio-wide setting on the Zahlungen pane.
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->boolean('client_reminders_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn(['overdue_reminders_sent', 'client_reminders_sent', 'overdue_reason']);
        });

        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropColumn('client_reminders_enabled');
        });
    }
};
