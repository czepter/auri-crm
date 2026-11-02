<?php

use App\Gallery\Support\GallerySlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('prefix', GallerySlug::MAX)->nullable()->after('slug');
        });

        $byCase = [];
        $taken = [];

        $galleries = DB::table('galleries')
            ->leftJoin('cases', 'cases.id', '=', 'galleries.case_id')
            ->orderBy('galleries.id')
            ->get(['galleries.id', 'galleries.case_id', 'cases.name as case_name']);

        foreach ($galleries as $gallery) {
            $prefix = $gallery->case_id === null
                ? Str::lower(Str::random(10))
                : $byCase[$gallery->case_id] ??= GallerySlug::unique($gallery->case_name ?? 'auftrag', fn ($candidate) => in_array($candidate, $taken, true));
            $taken[] = $prefix;

            DB::table('galleries')->where('id', $gallery->id)->update(['prefix' => $prefix]);
        }

        Schema::table('galleries', function (Blueprint $table) {
            $table->string('prefix', GallerySlug::MAX)->nullable(false)->change();
            $table->dropUnique(['slug']);
            $table->unique(['prefix', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropUnique(['prefix', 'slug']);
            $table->unique('slug');
            $table->dropColumn('prefix');
        });
    }
};
