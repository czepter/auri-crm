<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Download, link kopieren, per E-Mail senden — the split button on a
     * quote's own screen defaults to whichever of the three a staff member
     * reached for last. A column on the user rather than a cookie, the same
     * reasoning as `lead_view`: the choice survives a second machine.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_quote_action')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_quote_action');
        });
    }
};
