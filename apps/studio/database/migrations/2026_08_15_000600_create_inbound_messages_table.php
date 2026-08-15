<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-12: every inbound delivery, whether or not it threads.
 *
 * A mail that vanished silently is the failure this table exists to prevent.
 * Threading is the happy path and the triage tray is the rest, but both are
 * rows here first — so "the client says they replied and we never saw it"
 * has somewhere to be answered.
 *
 * `message_id` is the sender's own RFC 5322 Message-ID, and it is what makes
 * a replayed webhook delivery a no-op rather than a duplicate message. Unique
 * per tenant.
 *
 * No mailbox credential appears anywhere in this schema, and that is the
 * point of the whole story: the app's own hosting owns the inbound domain,
 * so there is nothing to store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('message_id')->nullable();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('subject')->nullable();
            $table->text('body');

            // The provider's verdicts, kept as it reported them. A mail that
            // failed DMARC is quarantined on the strength of this column, so
            // storing the reason beside the decision is what makes the
            // decision reviewable later.
            $table->string('spf')->nullable();
            $table->string('dkim')->nullable();
            $table->string('dmarc')->nullable();
            $table->integer('spam_score')->nullable();

            // threaded | triage | quarantined — App\Engagement\Enums\InboundMessageState.
            $table->string('state');
            $table->string('reason')->nullable();

            $table->foreignId('thread_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id_ref')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'message_id']);
            $table->index(['tenant_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_messages');
    }
};
