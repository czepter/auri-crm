<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-08: one row per firing. idempotency_key is a sha256 hex digest (64
 * chars) of the rule, the anchor (a Shoot for a per_shoot rule, a Project
 * for a primary_shoot one or for an event-triggered rule — see
 * AutomationRule's own docblock) and the trigger date — see
 * App\Automation\Models\AutomationRun::idempotencyKey(). The unique
 * constraint below is what actually stops a redeployed scheduler, or a
 * retried queued listener, from sending the same reminder twice:
 * App\Automation\Actions\FireAutomationRule claims this row with a plain
 * insert BEFORE it ever calls NotificationService, so a second caller racing
 * the same trigger loses the insert, not the send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->morphs('anchorable');

            $table->char('idempotency_key', 64);
            $table->timestampTz('fired_at');
            $table->string('status');
            $table->text('error')->nullable();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'idempotency_key'], 'automation_runs_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
