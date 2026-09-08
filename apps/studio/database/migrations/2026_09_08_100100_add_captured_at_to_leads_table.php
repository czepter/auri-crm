<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the inquiry reached the studio, which is only the same moment as
     * the row's creation when it was typed in live. A call taken last Tuesday
     * and entered today has always been recorded as today's — and the funnel
     * is counted per month, so the two dates cannot stay one column.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestampTz('captured_at')->nullable()->after('captured_via_other');
        });

        DB::table('leads')->update(['captured_at' => DB::raw('created_at')]);

        Schema::table('leads', function (Blueprint $table) {
            $table->timestampTz('captured_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('captured_at');
        });
    }
};
