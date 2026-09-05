<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The commitment. The framework's queue table was renamed to
        // queue_jobs in 0001_01_01_000002 so this name could be the domain's.
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // One job per project. Idempotency guards job creation rather than
            // the trigger, because ProjectBooked firing twice becomes a live
            // failure mode the moment P-05b adds a second entry point.
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();

            $table->timestampTz('booked_at');

            // Nullable only for the trigger that has no staff member behind it
            // — P-05b books on a signature. The manual path always names one,
            // which is the whole reason the column exists: without it the audit
            // log cannot answer "who decided this was booked".
            $table->foreignId('booked_by_id')->nullable()->constrained('users');

            $table->string('booked_via');
            $table->timestampsTz();

            // No pipeline status, per vision §3.1: a project whose engagement
            // shoot is delivered while the wedding is unshot has no single
            // state. P-04 derives board position from the shoots.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
