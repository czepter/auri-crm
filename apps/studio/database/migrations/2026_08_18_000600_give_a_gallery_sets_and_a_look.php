<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-19 (#255) asks for two decisions before the first commit. Both taken
     * here, with the reason:
     *
     * **Sets — a key on the asset entry, not a table.** `galleries.assets` is
     * a JSON array of objects and the entries have no ids to point a foreign
     * key at; a second table would have to stay in step with an array that
     * can be rewritten wholesale by an ingest. A `set` on the entry travels
     * with the picture, survives publish() into the version snapshot for free
     * (that is a copy of this very column), and an entry without one is in
     * the unnamed set, which is what "Alle Bilder" already means. The price
     * is that reordering sets is a rewrite of the array — acceptable at a
     * gallery's size, and the same price the array already pays for order.
     *
     * **Design — one jsonb column with the studio's defaults underneath.**
     * Cover variant, typeface, colour and grid are four values that are read
     * together, always, and never queried across galleries. Four columns
     * would be four migrations the first time a fifth choice arrives.
     * Null means "as the studio has it", so changing the studio default
     * still reaches every gallery that never overrode it — the same
     * three-valued rule the portal's own per-contact overrides use.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->jsonb('design')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('design');
        });
    }
};
