<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R39: a fourth situation alongside draft/published/withdrawn — the
     * studio has shared the address before there is anything behind it.
     * A timestamp, not an enum: it only means anything while the gallery
     * has no GalleryVersion yet (Gallery::isInPrerelease()), the same way
     * unpublished_at only means anything once one exists. Publishing
     * clears it; withdrawal does not need to, since isWithdrawn() is
     * checked first everywhere a guest can reach this gallery.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->timestampTz('prerelease_at')->nullable()->after('unpublished_at');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('prerelease_at');
        });
    }
};
