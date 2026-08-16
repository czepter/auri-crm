<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-05 (#224): the portal gets an account. Three things it had no
     * schema for.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // "Sign out on every device". There is no password to rotate
            // here (A-02 — a client proves an inbox, nothing else), and the
            // session driver is not something this feature should depend on,
            // so the check is an epoch: every session records when it began,
            // and one older than this is refused on its very next request —
            // the same shape portal_access_revoked_at already takes in
            // EnsureAudience.
            $table->timestampTz('sessions_valid_from')->nullable();
        });

        // Changing the e-mail changes the login. Confirmed through the NEW
        // address before anything is written, or a typo locks the client out
        // of the only way back in.
        Schema::create('contact_email_changes', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('new_email');
            // Hashed, same as every other credential this app puts in a URL:
            // a database dump must not hand out live links.
            $table->string('token_hash')->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'contact_id']);
        });

        // Art. 15 and Art. 17 as requests rather than self-service buttons:
        // the studio owes an answer inside a deadline, and a request nobody
        // at the studio ever sees is worse than no button at all. X-04's
        // ExportContactDsgvoData and EraseContactDsgvo stay the admin-side
        // actions that answer one.
        Schema::create('dsgvo_requests', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->timestampTz('handled_at')->nullable();
            $table->foreignId('handled_by_id')->nullable()->constrained('users');
            $table->timestampsTz();

            // No unique index on (contact_id, kind, handled_at): Postgres
            // treats NULLs as distinct, so it would not stop the second open
            // request it looks like it stops. AccountController checks
            // instead — see requestDsgvo().
            $table->index(['tenant_id', 'handled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsgvo_requests');
        Schema::dropIfExists('contact_email_changes');
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('sessions_valid_from'));
    }
};
