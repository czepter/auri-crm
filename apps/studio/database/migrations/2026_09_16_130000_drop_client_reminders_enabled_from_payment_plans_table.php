<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client payment reminders are not a per-plan decision; the studio-wide
 * switch under Zahlungen is the only one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropColumn('client_reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->boolean('client_reminders_enabled')->default(true);
        });
    }
};
