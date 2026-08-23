<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * removed_from_draft_at statt SoftDeletes: BuildGalleryZip löst die Keys
     * einer veröffentlichten Version über Asset-Zeilen auf, ein globaler
     * Scope machte sie dort unsichtbar und bräche das ZIP einer bereits
     * ausgelieferten Galerie. Der Name sagt, was passiert ist — das Bild hat
     * den Entwurf verlassen, es ist nicht gestorben.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable()->after('gallery_id')
                ->constrained('gallery_sets')->cascadeOnDelete();
            $table->timestampTz('removed_from_draft_at')->nullable()->after('error');
        });

        // Nullable for now: IngestCompletedUpload does not yet choose a set on
        // write. Task 4 rewrites it to do so and flips this column not null
        // once every writer sets it.
        DB::statement(<<<'SQL'
            update assets a
               set gallery_set_id = s.id
              from gallery_sets s
             where s.gallery_id = a.gallery_id
               and s.position = 0
        SQL);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_set_id');
            $table->dropColumn('removed_from_draft_at');
        });
    }
};
