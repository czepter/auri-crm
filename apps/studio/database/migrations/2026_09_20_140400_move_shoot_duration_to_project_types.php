<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_types', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->default(120);
        });

        DB::statement('alter table project_types add constraint project_types_duration_check check (duration_minutes > 0)');

        DB::statement('update project_types set duration_minutes = studio_settings.default_shoot_duration_minutes from studio_settings where studio_settings.tenant_id = project_types.tenant_id');

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('default_shoot_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->unsignedInteger('default_shoot_duration_minutes')->default(120);
        });

        DB::statement('alter table studio_settings add constraint studio_settings_default_shoot_duration_check check (default_shoot_duration_minutes > 0)');

        Schema::table('project_types', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
