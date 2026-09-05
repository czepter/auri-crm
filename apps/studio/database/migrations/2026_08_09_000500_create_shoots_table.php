<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shoots', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('label')->nullable();

            // A date column, never a timestamp: a shooting day is a calendar
            // day and must not shift with a timezone (vision §8). Nullable
            // because a portrait lead legitimately has no date yet — and a
            // required date would force staff to invent one, corrupting the
            // very column the availability check reads.
            $table->date('date')->nullable();
            $table->time('call_time')->nullable();
            $table->time('end_time')->nullable();

            // Not collected at capture; the project screen fills them in
            // sprint 3.
            $table->string('venue_name')->nullable();
            $table->string('venue_city')->nullable();

            $table->string('status')->default('requested');
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            // The availability scan: one indexed lookup per date.
            $table->index(['tenant_id', 'date']);
        });

        // Vision §3.1: exactly one shoot per project is primary. Set at intake,
        // never derived — a rule like "earliest date" would be wrong for
        // exactly the case that motivates the model, since the engagement
        // shoot is the earliest date and is never the headline occasion.
        DB::statement(
            'create unique index shoots_one_primary
             on shoots (project_id) where is_primary'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('shoots');
    }
};
