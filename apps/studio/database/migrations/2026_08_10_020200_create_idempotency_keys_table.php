<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I-03: one row per (tenant, ApiToken, Idempotency-Key) a write route has
 * ever seen. App\Http\Middleware\EnsureIdempotentWrite claims this row with
 * a plain insert BEFORE the route's controller ever runs — the unique
 * constraint below is what actually stops two overlapping callers racing the
 * same key from both executing the write, the same insert-first pattern
 * automation_runs' own migration already uses for the same reason. Once the
 * request completes, the middleware fills in response_status/response_body,
 * which is what makes a replay return the exact response the first call
 * produced rather than merely skip the side effect a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('api_token_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key');

            // sha256 of method+path+body — catches the same key replayed
            // against a different request, which is a caller bug, not a
            // legitimate replay.
            $table->char('request_fingerprint', 64);

            // Null until the wrapped request finishes; see the middleware's
            // own docblock for what a still-null row means to a racing
            // caller.
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'api_token_id', 'idempotency_key'], 'idempotency_keys_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
