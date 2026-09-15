<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // formal is what every client mail said before this column existed.
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('client_register')->default('formal');
        });

        DB::statement("alter table studio_settings add constraint studio_settings_client_register_check check (client_register in ('formal', 'informal'))");
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('client_register');
        });
    }
};
