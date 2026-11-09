<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_payment_stages', function (Blueprint $blueprint) {
            $blueprint->string('note')->nullable();
            $blueprint->date('due_on')->nullable();
        });

        Schema::table('payment_installments', function (Blueprint $blueprint) {
            $blueprint->string('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $blueprint) {
            $blueprint->dropColumn('note');
        });

        Schema::table('quote_payment_stages', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['note', 'due_on']);
        });
    }
};
