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
            $table->string('contract_mode')->default('on_demand');
        });

        DB::statement("alter table studio_settings add constraint studio_settings_contract_mode_check check (contract_mode in ('always', 'on_demand', 'off'))");
    }

    public function down(): void
    {
        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('contract_mode'));
    }
};
