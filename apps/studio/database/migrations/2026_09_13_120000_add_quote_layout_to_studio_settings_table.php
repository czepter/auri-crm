<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('quote_layout')->default('classic');
        });

        DB::statement("alter table studio_settings add constraint studio_settings_quote_layout_check check (quote_layout in ('classic', 'minimal', 'letter', 'header_band', 'structured'))");
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('quote_layout');
        });
    }
};
