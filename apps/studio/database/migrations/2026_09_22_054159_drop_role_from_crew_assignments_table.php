<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shooting day names who is on it, not what they do there — the studio
 * splits that by shoot already (lead vs. second shooter is which shoot they
 * are on, an engagement session vs. the wedding day), so the column never
 * answered a question the assignment itself did not already answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_assignments', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('crew_assignments', function (Blueprint $table) {
            $table->string('role')->default('lead_photographer');
        });
    }
};
