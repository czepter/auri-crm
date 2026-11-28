<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A draft the payment scan writes belongs to no rule.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->foreignId('automation_rule_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('automation_runs')->whereNull('automation_rule_id')->delete();

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->foreignId('automation_rule_id')->nullable(false)->change();
        });
    }
};
