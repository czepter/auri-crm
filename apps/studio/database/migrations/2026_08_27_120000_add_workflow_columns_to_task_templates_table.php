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
            // The project type's key, as projects.type holds it. Null applies
            // to every type: a crew briefing is owed for all of them,
            // "Familienliste" only for a wedding.
            $table->string('project_type')->nullable()->after('audience');

            // Which pipeline the stage below belongs to, and the stage's own
            // key — the same one leads.stage and shoots.status hold. The
            // defaults backfill every existing row to what it already meant:
            // applied at booking, counted from the shoot's date.
            $table->string('pipeline')->default('project')->after('project_type');
            $table->string('stage')->default('booked')->after('pipeline');
            $table->string('anchor')->default('shoot')->after('day_offset');

            $table->index(['tenant_id', 'pipeline', 'stage']);
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
            $table->dropIndex(['tenant_id', 'pipeline', 'stage']);
            $table->dropColumn(['project_type', 'pipeline', 'stage', 'anchor']);
        });
    }
};
