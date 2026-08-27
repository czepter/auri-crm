<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            // Null applies to every type. A crew briefing is owed for all of
            // them; "Familienliste" only for a wedding.
            $table->string('project_type')->nullable()->after('audience');

            // Which pipeline the stage below belongs to, and the stage itself.
            // The default backfills every existing row to what it already
            // meant: applied at booking, counted from the shoot's date.
            $table->string('stage_kind')->default('project')->after('project_type');
            $table->string('stage')->default('booked')->after('stage_kind');
            $table->string('anchor')->default('shoot')->after('day_offset');

            $table->index(['tenant_id', 'stage_kind', 'stage']);
        });

        // The tasks table already guarantees one task per template per shoot.
        // A lead-stage task has no shoot, and NULLs do not collide in a unique
        // index — so re-entering a stage would produce the task a second time.
        DB::statement(
            'CREATE UNIQUE INDEX tasks_template_project_unique ON tasks (task_template_id, project_id) WHERE shoot_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX tasks_template_project_unique');

        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'stage_kind', 'stage']);
            $table->dropColumn(['project_type', 'stage_kind', 'stage', 'anchor']);
        });
    }
};
