<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One plan per job — the unique index on job_id is the whole rule.
        // Scoped to the Job rather than the Project: a project without a Job
        // has no plan, because the plan is a property of the booking, not of
        // the pursuit (M-03).
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('job_id')->unique()->constrained()->cascadeOnDelete();

            // Minor units (cents), same convention as quotes.total. The
            // authoritative figure this plan's installments must sum to —
            // see the trigger in the next migration — set once at creation
            // and only ever changed alongside the installments themselves
            // (App\Payments\Actions\UpdatePaymentPlan), never independently.
            $table->unsignedInteger('total');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
