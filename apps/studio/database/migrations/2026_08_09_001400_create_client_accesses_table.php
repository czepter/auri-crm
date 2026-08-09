<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_accesses', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // Contact-scoped, not project-scoped: one link cannot become
            // access to a project the contact was later removed from, and a
            // repeat client signs in once for both of their projects.
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            // The hash, never the token — the same shape StaffInvitation
            // established. A leaked database must not hand over live links.
            $table->string('token_hash', 64)->unique();

            $table->timestampTz('expires_at');

            // Single use. Stamped inside the same transaction that logs the
            // contact in, so a link raced twice can only win once.
            $table->timestampTz('consumed_at')->nullable();

            // Who asked. Kept for the audit trail and for answering "was this
            // link requested from somewhere odd" after the fact.
            $table->string('requested_ip', 45)->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_accesses');
    }
};
