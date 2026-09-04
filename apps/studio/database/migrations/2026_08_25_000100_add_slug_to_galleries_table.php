<?php

use App\Gallery\Support\GallerySlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A readable address, generated once per gallery from its title.
     * Existing rows get one here, in the order they were created, so the
     * backfill is deterministic — GalleryIngestTargetTest's
     * migrate:rollback --path pattern is what proves this over rows that
     * predate the column, not a fresh RefreshDatabase run.
     *
     * Global uniqueness, not per tenant: see GallerySlug's own doc comment.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('slug', GallerySlug::MAX)->nullable()->after('title');
        });

        $taken = [];

        foreach (DB::table('galleries')->orderBy('id')->select(['id', 'title'])->get() as $gallery) {
            $slug = GallerySlug::unique($gallery->title ?? 'galerie', fn ($candidate) => in_array($candidate, $taken, true));
            $taken[] = $slug;

            DB::table('galleries')->where('id', $gallery->id)->update(['slug' => $slug]);
        }

        Schema::table('galleries', function (Blueprint $table) {
            $table->string('slug', GallerySlug::MAX)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
