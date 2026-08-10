<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I-04: one row per delivery attempt cycle of one endpoint, one event. The
 * exact `payload` is kept (not re-derived from the notification later) so
 * both App\Integration\Jobs\DeliverWebhookDelivery's own retries and a hand
 * replay (`webhook:redeliver`) resend the identical bytes that were signed
 * the first time — a partial receiver outage should never mean the retried
 * body is "close enough" to what would have arrived on time.
 *
 * `attempts`/`next_retry_at` carry App\Integration\Jobs\
 * DeliverWebhookDelivery's own backoff schedule; `status` only ever holds
 * pending (more attempts left, or none tried yet), delivered (2xx seen), or
 * failed (attempts exhausted without one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();

            $table->string('event_type');
            $table->jsonb('payload');
            $table->string('signature')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('next_retry_at')->nullable();
            $table->string('status')->default('pending');

            $table->timestampsTz();

            $table->index(['tenant_id', 'webhook_endpoint_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
