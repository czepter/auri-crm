<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * G-03 (#231): what gets downloaded is a list.
     *
     * ZipBuildScope had one case, All, and the unique index below assumed it
     * — two builds of two different lists at the same size would have
     * collided on it. The list is nullable because All is still a real
     * answer and the commonest one.
     */
    public function up(): void
    {
        Schema::table('zip_builds', function (Blueprint $table) {
            $table->foreignId('gallery_list_id')->nullable()->constrained()->cascadeOnDelete();

            $table->dropUnique(['tenant_id', 'gallery_version_id', 'scope', 'size']);
            $table->unique(['tenant_id', 'gallery_version_id', 'scope', 'size', 'gallery_list_id'], 'zip_builds_unique_request');
        });
    }

    public function down(): void
    {
        Schema::table('zip_builds', function (Blueprint $table) {
            $table->dropUnique('zip_builds_unique_request');
            $table->dropConstrainedForeignId('gallery_list_id');
            $table->unique(['tenant_id', 'gallery_version_id', 'scope', 'size']);
        });
    }
};
