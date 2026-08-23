<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every gallery holding a multipart_uploads row already has a set by
     * now — 2026_08_24_000500's own backfill created a Highlights one for
     * any gallery that had none, external or not. StartOrResumeMultipartUpload
     * has filled this column on every row it creates since Task 4 landed;
     * this closes the same gap 000500 closed for assets, so
     * IngestCompletedUpload's failure branch can no longer save a
     * MultipartUpload-derived Asset off a session with no set of its own —
     * that state is now impossible rather than merely unlikely.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            update multipart_uploads u
               set gallery_set_id = s.id
              from gallery_sets s
             where s.gallery_id = u.gallery_id
               and s.position = 0
               and u.gallery_set_id is null
        SQL);

        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable()->change();
        });
    }
};
