<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #457: both are statistics, not work, and capture must not wait on them.
 * Null reads as "unbekannt" in the funnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leads ALTER COLUMN source DROP NOT NULL');
        DB::statement('ALTER TABLE leads ALTER COLUMN captured_via DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE leads SET source = 'other' WHERE source IS NULL");
        DB::statement("UPDATE leads SET captured_via = 'other' WHERE captured_via IS NULL");
        DB::statement('ALTER TABLE leads ALTER COLUMN source SET NOT NULL');
        DB::statement('ALTER TABLE leads ALTER COLUMN captured_via SET NOT NULL');
    }
};
