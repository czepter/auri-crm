<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-03 (#222)'s last open piece: the questionnaire the inquiry screen
     * links to was not fillable, because E-02/E-03 built the whole family
     * admin-only.
     *
     * Sharing is deliberate and per questionnaire, not a blanket "the client
     * sees every form on their project" — a studio keeps internal checklists
     * on the same table (FormInstance's own doc comment: seeded from a
     * template or started blank, then personalised freely), and those are
     * nobody else's business. Same shape as projects.portal_visible: the
     * absence of a stamp is the closed state.
     */
    public function up(): void
    {
        // P-09 (#228): "Vorschau als Kunde". A preview is a magic link the
        // studio mints for itself, so it reuses the one this app already
        // has rather than a second single-use-token mechanism beside it —
        // expiry, single use and revocation all come from ClientAccess
        // unchanged. The staff member on the row is what turns the session
        // it opens read-only (EnsureAudience) and what the audit trail
        // names.
        Schema::table('client_accesses', function (Blueprint $table) {
            $table->foreignId('preview_by_id')->nullable()->constrained('users');
        });

        Schema::table('form_instances', function (Blueprint $table) {
            $table->timestampTz('shared_at')->nullable();
        });

        Schema::table('form_answers', function (Blueprint $table) {
            // Who wrote this version. Exactly one of the two, or neither for
            // the rows E-03 wrote before this column existed — a staff answer
            // recorded on the phone and the client's own typing are different
            // facts, and an append-only table is the one place that
            // distinction can still be read years later.
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('form_answers', fn (Blueprint $table) => $table->dropColumn(['contact_id', 'author_id']));
        Schema::table('form_instances', fn (Blueprint $table) => $table->dropColumn('shared_at'));
        Schema::table('client_accesses', fn (Blueprint $table) => $table->dropColumn('preview_by_id'));
    }
};
