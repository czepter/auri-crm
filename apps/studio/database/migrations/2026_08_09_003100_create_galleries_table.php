<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Nullable: a delivery gallery frequently spans the whole project
            // rather than one shoot (G-05) — the wedding-day gallery, for a
            // project with an engagement shoot and a wedding-day shoot, has
            // nowhere truthful to point if this were required.
            $table->foreignId('shoot_id')->nullable()->constrained()->cascadeOnDelete();

            // Draft state — freely editable, never what a guest is served.
            // publish() reads these to write an immutable GalleryVersion; see
            // Gallery::publish() for why editing these never touches a row
            // already published.
            $table->string('title')->nullable();
            $table->json('assets')->default('[]');

            // X-02: eine Galerie, die anderswo liegt — die Verwaltung führt
            // sie, gehostet wird sie nicht hier.
            $table->boolean('is_external')->default(false);
            $table->string('external_url')->nullable();

            // G-06's three gates. A null secret means the gate is off — no
            // separate "required" flag to drift out of sync with it. Every
            // gate carries its own version, bumped on every rotate() call and
            // nothing else; that counter, not the secret itself, is what a
            // granted claim is checked against (App\Gallery\Support\
            // GalleryAccessSession), so rotating one gate invalidates only
            // the claims minted before it.
            //
            // C-17 (#253): stored recoverably and shown in the admin in
            // clear. Both values are handed to guests anyway — by mail, by
            // WhatsApp, over the phone. A studio that cannot look one up sets
            // a new one on every enquiry and locks out everybody holding the
            // old. What it costs, unvarnished: a database dump now contains
            // every gallery password and PIN. Encryption with APP_KEY defends
            // against the stolen dump, not against an attacker who can run
            // the application. Staff login passwords and
            // client_accesses.token_hash are not covered by this and stay
            // hashes — they are never shown.
            $table->text('view_password')->nullable();
            $table->unsignedInteger('view_password_version')->default(1);

            $table->text('download_pin')->nullable();
            $table->unsignedInteger('download_pin_version')->default(1);

            // The email gate has no secret to hash — it establishes identity,
            // not possession — so it keeps the boolean the other two don't
            // need. The version still exists: rotating it forces every
            // already-verified guest to reverify.
            $table->boolean('requires_verified_email')->default(false);
            $table->unsignedInteger('email_verification_version')->default(1);

            // Wann eine ausgelieferte Galerie aufhört, erreichbar zu sein.
            // Null heißt: bleibt stehen.
            $table->timestampTz('expires_at')->nullable();

            // C-19 (#255): cover variant, typeface, colour and grid. Null
            // means "as the studio has it", so a studio changing its own
            // default still reaches every gallery that never overrode one.
            $table->jsonb('design')->nullable();

            // P-06's "Aufrufe". DownloadEvent has only ever counted
            // downloads, so nothing told a couple whether the link they
            // shared was opened at all. Counted once per visitor session
            // rather than per request — see GalleryPasswordController.
            $table->unsignedInteger('view_count')->default(0);

            // Ships false-by-default and inert: nothing yet reads this flag to
            // gate anything, because delivery/download flows are future
            // stories. What keeps it non-decorative is
            // App\Gallery\Listeners\RecomputeGalleryBalanceSettled, wired to
            // the real InstallmentPaid event M-05 already delivers.
            $table->boolean('requires_settled_balance')->default(false);
            $table->boolean('balance_settled')->default(false);

            // P-09's three portal switches, on the gallery rather than in the
            // settings store: they are answered per delivery, and the studio
            // default only decides what a new one is born with. Not
            // three-valued — a gallery either offers the thing or it does
            // not, and there is no level below it to inherit from.
            $table->boolean('downloads_enabled')->default(true);
            $table->boolean('lists_enabled')->default(true);
            $table->boolean('comments_enabled')->default(true);

            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
