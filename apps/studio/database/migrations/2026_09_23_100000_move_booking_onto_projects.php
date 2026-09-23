<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #519: the booking becomes three columns on the project and jobs goes away.
 * payment_plans restricts the delete now: through jobs it cascaded, and a
 * deleted project took its plan, installments and invoice numbers with it.
 *
 * down() rebuilds the schema only; the bookings are not carried back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestampTz('booked_at')->nullable();
            // Null on a booked project too: a contract signature books with no staff member behind it.
            $table->foreignId('booked_by_id')->nullable()->constrained('users');
            $table->string('booked_via')->nullable();
        });

        DB::statement('alter table projects add constraint projects_booking_check check ((booked_at is null) = (booked_via is null) and (booked_by_id is null or booked_at is not null))');

        DB::statement('update projects set booked_at = jobs.booked_at, booked_by_id = jobs.booked_by_id, booked_via = jobs.booked_via from jobs where jobs.project_id = projects.id');

        Schema::table('payment_plans', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });

        DB::statement('update payment_plans set project_id = jobs.project_id from jobs where jobs.id = payment_plans.job_id');
        DB::statement('alter table payment_plans alter column project_id set not null');

        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_id');
        });

        Schema::drop('jobs');
    }

    public function down(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestampTz('booked_at');
            $table->foreignId('booked_by_id')->nullable()->constrained('users');
            $table->string('booked_via');
            $table->timestampsTz();
        });

        Schema::table('payment_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('job_id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booked_by_id');
            $table->dropColumn(['booked_at', 'booked_via']);
        });
    }
};
