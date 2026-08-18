<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-21 (#256): whether a gallery was ever opened.
     *
     * There was no way to answer it. A visitor with the view password got a
     * versioned claim in the session (GalleryAccessSession::grant) and no row
     * came into being; for a signed-in contact only the access link was
     * stamped (client_accesses.consumed_at), never the visit. So "opened /
     * not opened" on the contact sheet, the `Aufgerufen` figure in the
     * gallery's head and the access sheet in the activity all had nothing to
     * read from. galleries.view_count counted, but a bare number cannot say
     * who or when.
     *
     * The three questions the issue asks answered, in the order it asks them:
     *
     * **What is a view.** The first opening per visitor per day, not every
     * page load — the same folding download_events already does over
     * (requester_identifier, request_date). A couple showing their parents
     * the pictures on a laptop is one visit, not forty, and the unique index
     * below is what enforces that rather than a count nobody can verify.
     *
     * **Who the visitor is.** The portal session's own guest id
     * (GalleryVisitor::guestId), and the Contact where one is signed in. A
     * password-only visitor stays nameless on purpose: inventing a label for
     * a row that has none would explain nothing on the screen, so the screen
     * says "Gast" and means it.
     *
     * **How long to keep it.** Visit data is personal. Kept as long as the
     * gallery is reachable and swept with it — the expiry
     * (galleries.expires_at) is the retention this feature inherits rather
     * than a second clock nobody would remember to wind.
     */
    public function up(): void
    {
        Schema::create('gallery_views', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_identifier');
            $table->date('viewed_on');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'visitor_identifier', 'viewed_on'], 'gallery_views_once_a_day');
            $table->index(['tenant_id', 'gallery_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_views');
    }
};
