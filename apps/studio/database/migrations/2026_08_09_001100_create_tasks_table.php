<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Anchored to the shoot, not the project. A project with an
            // engagement session and a wedding needs its "confirm timeline"
            // task against the shoot it belongs to — the same anchoring
            // problem E-08 solves generally.
            //
            // Nullable because a task typed by hand on the project need not
            // belong to any one occasion.
            $table->foreignId('shoot_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('audience');

            // A date, matching shoots.date: a due date is a calendar day and
            // must not shift with a timezone (vision §8).
            $table->date('due_on')->nullable();

            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users');

            // Which template produced it, so applying the set twice cannot
            // duplicate a row. Null for a task somebody typed.
            $table->foreignId('task_template_id')->nullable()->constrained()->nullOnDelete();

            $table->timestampsTz();

            // The Übersicht asks "what is open and due soon", always within one
            // tenant.
            $table->index(['tenant_id', 'completed_at', 'due_on']);

            // One task per template per shoot. Booking is idempotent at the
            // Job, but P-05b adds a second trigger and E-08 will re-apply
            // templates — the guarantee belongs in the database, not in the
            // caller that remembers to check.
            $table->unique(['task_template_id', 'shoot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
