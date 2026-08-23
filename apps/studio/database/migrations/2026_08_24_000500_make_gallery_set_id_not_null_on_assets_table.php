<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * App\Media\Actions\IngestCompletedUpload now fills gallery_set_id on
     * every Asset it creates, success and Failed alike — the column Task 2
     * left nullable because that writer didn't yet becomes a guarantee here.
     * The backfill only catches rows an older build of that action wrote
     * before this deploy; a fresh table has nothing to touch.
     *
     * An external gallery never gets a MultipartUpload — its
     * gallery_set_id validation has no set to resolve, since Gallery::
     * booted() creates none for one — so no Asset row can carry one either;
     * nothing here needs to stay nullable for that case.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            update assets a
               set gallery_set_id = s.id
              from gallery_sets s
             where s.gallery_id = a.gallery_id
               and s.position = 0
               and a.gallery_set_id is null
        SQL);

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable()->change();
        });
    }
};
