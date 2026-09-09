<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * G-05: the download gate asks Project::balanceSettled() when it decides,
     * so this cached copy has no reader left — and a gallery created after
     * the last instalment cleared kept its false default for ever, because
     * no further InstallmentPaid was coming to correct it.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('balance_settled');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('balance_settled')->default(false);
        });
    }
};
