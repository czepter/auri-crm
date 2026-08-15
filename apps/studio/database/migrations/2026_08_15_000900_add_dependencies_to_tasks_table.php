<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-12: a task that cannot start until another finishes.
 *
 * S-03's tasks are flat — each an offset from its shoot's date, all of them
 * independent. Real studio work is not: the gallery is not delivered before
 * the selection is made, and the selection is not made before the preview is
 * sent.
 *
 * `nullOnDelete` rather than cascade: deleting a task that something else was
 * waiting on must unblock the dependent, never delete it. Losing the work
 * because its predecessor was removed would be a strange way to find out.
 *
 * The same column on task_templates, so applying a template on the transition
 * into `booked` produces the chain rather than a flat set someone reorders by
 * hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('depends_on_task_id')->nullable()->constrained('tasks')->nullOnDelete();
        });

        Schema::table('task_templates', function (Blueprint $table) {
            $table->foreignId('depends_on_template_id')->nullable()->constrained('task_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depends_on_task_id');
        });

        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depends_on_template_id');
        });
    }
};
