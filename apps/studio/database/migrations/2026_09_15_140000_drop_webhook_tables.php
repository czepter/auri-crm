<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }

    public function down(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('url');
            $table->string('secret');
            $table->boolean('active')->default(true);
            $table->jsonb('subscribed_events')->default('[]');

            $table->timestampsTz();
        });

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
};
