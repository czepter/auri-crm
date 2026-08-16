<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-11: what the studio sent a client, kept where the conversation is.
 *
 * E-06 gave threads and messages a home per project, and staff replies have
 * lived there since — but they never left the portal. A client who does not
 * log in never learns there is an answer waiting, and the record of what was
 * actually said lives in whatever mailbox `mail_bcc` points at.
 *
 * `email_message_id` is the RFC 5322 Message-ID of the mail that carried the
 * row. Unique per tenant, and deliberately not globally: two tenants could
 * in principle mint the same id, and the same tenant doing it twice is what
 * must be impossible. This is the column E-12 threads inbound replies on,
 * which is why that story is small.
 *
 * `delivery_state` is what the transport reported. Bounces are E-12's to
 * write — nothing today can hear one — so until then this holds `sent` or
 * `failed` and nothing else. Nullable, because a message the client wrote
 * was never delivered by us at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('email_message_id')->nullable();
            $table->string('delivery_state')->nullable();

            $table->unique(['tenant_id', 'email_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email_message_id']);
            $table->dropColumn(['email_message_id', 'delivery_state']);
        });
    }
};
