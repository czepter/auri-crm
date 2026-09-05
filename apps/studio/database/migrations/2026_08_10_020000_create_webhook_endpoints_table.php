<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I-04: a tenant's subscription to outbound webhooks. `secret` is stored in
 * plaintext (unlike a Sanctum token, which only ever stores a hash) because
 * WebhookTransport needs the raw key back on every delivery to compute an
 * HMAC-SHA256 signature — there is no way to sign a new payload from a hash.
 * `subscribed_events` names which of NotificationService's fan-outs this
 * endpoint wants, matched against App\Notifications\Contracts\
 * WebhookSubscribable::webhookEventType() — see WebhookTransport's own
 * docblock. A bare string array rather than a foreign vocabulary table: the
 * set of event types is small and grows one Event::listen at a time in
 * AppServiceProvider, not administered independently.
 */
return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
