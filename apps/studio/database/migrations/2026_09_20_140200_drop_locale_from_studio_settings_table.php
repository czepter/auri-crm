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
            $table->dropColumn('locale');
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('locale')->nullable();
        });

        DB::statement("alter table studio_settings add constraint studio_settings_locale_check check (locale is null or locale in ('de', 'de_DE', 'de_AT', 'de_CH'))");
    }
};
