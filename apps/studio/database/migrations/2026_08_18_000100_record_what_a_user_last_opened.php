<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-03 (#237): the command palette opens with what one has just been
     * looking at, rather than an empty mask.
     *
     * Per user, not per studio. In a two-person studio the difference is
     * small; on a shared account "per studio" fills the palette with someone
     * else's afternoon, and the whole value of the list is that every line in
     * it is one you put there.
     *
     * One row per (user, subject): revisiting rewrites the timestamp instead
     * of pushing a duplicate, which is what keeps twenty rows twenty
     * different things. `title` is copied rather than joined — the palette
     * reads four unrelated tables and a stale name on a row nobody has opened
     * since it was renamed is cheaper than four joins on every keystroke of
     * ⌘K. It refreshes the next time the record is opened.
     */
    public function up(): void
    {
        Schema::create('recent_views', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('subject_id');
            $table->string('title');
            $table->timestampTz('viewed_at');

            // Keyed without `kind`: an inquiry that gets booked becomes a
            // project, and the palette holds one row per record rather than
            // one per phase it has been through.
            $table->unique(['tenant_id', 'user_id', 'subject_id']);
            $table->index(['tenant_id', 'user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recent_views');
    }
};
