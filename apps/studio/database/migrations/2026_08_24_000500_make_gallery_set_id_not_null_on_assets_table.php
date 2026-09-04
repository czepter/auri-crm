<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    /**
     * App\Media\Actions\IngestCompletedUpload now fills gallery_set_id on
     * every Asset it creates, success and Failed alike — the column Task 2
     * left nullable because that writer didn't yet becomes a guarantee here.
     *
     * Before this deploy, nothing stopped an upload into an external
     * gallery (no route or policy guard ever checked is_external — closing
     * that gap is MultipartUploadController::store()'s new
     * gallery_set_id lookup itself, not this migration) so a real gallery
     * can already hold Asset or MultipartUpload rows and no set at all, the
     * same hole Gallery::booted() leaves for one born external. Those
     * galleries get their Highlights set here — the same shape
     * 2026_08_24_000100 gave every gallery that existed before sets did —
     * before either backfill runs.
     */
    public function up(): void
    {
        $now = now();

        $orphaned = DB::table('galleries as g')
            ->select(['g.id', 'g.tenant_id'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('gallery_sets as s')->whereColumn('s.gallery_id', 'g.id');
            })
            ->where(function ($query) {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('assets as a')->whereColumn('a.gallery_id', 'g.id');
                })->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('multipart_uploads as u')->whereColumn('u.gallery_id', 'g.id');
                });
            })
            ->orderBy('g.id')
            ->get();

        foreach ($orphaned as $gallery) {
            DB::table('gallery_sets')->insert([
                'tenant_id' => $gallery->tenant_id,
                'public_id' => (string) new Cuid2,
                'gallery_id' => $gallery->id,
                'name' => 'Highlights',
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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
