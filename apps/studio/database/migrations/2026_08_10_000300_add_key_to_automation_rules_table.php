<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-09: identifies a row as "the one seeded from this config/automation.php
 * entry" so App\Automation\Console\Commands\SeedDefaultAutomationRulesCommand
 * can re-run without duplicating rows, and so a tenant's edit to that row's
 * content survives a re-seed. Null on a rule a tenant creates by hand —
 * nothing outside the seed command ever writes this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->string('key')->nullable()->after('public_id');
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->dropColumn('key');
        });
    }
};
