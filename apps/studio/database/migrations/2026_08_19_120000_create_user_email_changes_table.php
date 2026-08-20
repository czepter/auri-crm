<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The staff side of contact_email_changes. Same reasoning, and a harder
     * consequence: a client locked out of the wrong address can still be
     * reached by the studio, while a studio account has nobody above it —
     * /team invites, it never edits an address.
     *
     * Its own table rather than a morph on the portal one: that table's
     * `live` scope reads portal_access_revoked_at off the contact, which is
     * a column no staff account has.
     */
    public function up(): void
    {
        Schema::create('user_email_changes', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('new_email');
            $table->string('token_hash')->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_email_changes');
    }
};
