<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('pricing_mode')->default('classic')->after('status');
        });

        DB::table('quotes')->whereNotNull('hourly_rate')->update(['pricing_mode' => 'choice']);
    }

    public function down(): void
    {
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('pricing_mode'));
    }
};
