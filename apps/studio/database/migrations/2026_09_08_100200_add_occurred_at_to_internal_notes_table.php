<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable, and beside created_at rather than over it: a note carrying a
     * date the studio typed is a different fact from when the row was
     * written, and overwriting the second to move the first would leave the
     * audit trail lying about itself.
     */
    public function up(): void
    {
        Schema::table('internal_notes', function (Blueprint $table) {
            $table->timestampTz('occurred_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('internal_notes', function (Blueprint $table) {
            $table->dropColumn('occurred_at');
        });
    }
};
