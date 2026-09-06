<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q-08: the same five columns on both, because a Package is the template
     * and a Quote the copy — Package::copyItems() keeps them apart, so a
     * package stays a dead copy and never a live reference.
     *
     * hourly_rate null is the switch the whole round hangs on: every quote
     * that exists today has it null and stays a list of positions. Nothing
     * is backfilled, and there is no flag beside it.
     */
    public function up(): void
    {
        foreach (['quotes', 'packages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->integer('hourly_rate')->nullable();
                $blueprint->unsignedSmallInteger('min_hours')->nullable();
                $blueprint->unsignedSmallInteger('max_hours')->nullable();
                $blueprint->unsignedSmallInteger('images_per_hour')->nullable();
                $blueprint->unsignedSmallInteger('hours')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'packages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours']);
            });
        }
    }
};
