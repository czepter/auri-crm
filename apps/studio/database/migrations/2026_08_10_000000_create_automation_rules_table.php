<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-08: a data-driven rule, either domain-event or date-offset triggered —
 * see App\Automation\Enums\AutomationTriggerType. Exactly one of
 * (event_class) or (anchor, offset_days, direction) is populated per row,
 * matching which trigger_type it is; enforced in
 * App\Automation\Models\AutomationRule::booted() rather than a CHECK
 * constraint, since nothing outside this app's own actions ever writes this
 * table.
 *
 * The action this story ships is fixed and singular — "send a notification
 * via NotificationService" — so it lives as two plain columns rather than a
 * generic action_type/payload pair: there is nothing yet for a second action
 * kind to select between. OQ-7 (n8n driving P6's write API) is the seam a
 * future action kind would widen this table for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('name');

            $table->string('trigger_type');
            $table->string('event_class')->nullable();
            $table->string('anchor')->nullable();
            $table->unsignedSmallInteger('offset_days')->nullable();
            $table->string('direction')->nullable();

            $table->string('notification_subject');
            $table->text('notification_body');

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            // The sweep's own query (active, date-offset rules) and the
            // event listener's (active rules for one event class).
            $table->index(['tenant_id', 'is_active', 'trigger_type']);
            $table->index(['tenant_id', 'event_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
