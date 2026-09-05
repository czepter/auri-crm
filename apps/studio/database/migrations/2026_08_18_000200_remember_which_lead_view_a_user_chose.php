<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-07 (#241): board or table, remembered.
     *
     * A column on the user rather than a cookie or local storage, so the
     * choice survives the iPad, a second machine and a reinstall. `?ansicht=`
     * still outranks it — that one is shareable, and a link someone was sent
     * has to open the view it was sent as.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lead_view')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lead_view');
        });
    }
};
