<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #418: the plan is closed. Nothing chases it, nothing charges on it and
 * nothing edits it after this — see App\Payments\Actions\SettlePaymentPlan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->timestamp('settled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropColumn('settled_at');
        });
    }
};
