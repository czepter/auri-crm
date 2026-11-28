<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_download_links', function (Blueprint $table) {
            $table->foreignId('zip_build_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_guest_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_download_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zip_build_id');
            $table->dropConstrainedForeignId('gallery_guest_id');
        });
    }
};
