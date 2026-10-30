<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zip_build_parts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('zip_build_id')->constrained()->cascadeOnDelete();

            // 1-based, in the order App\Gallery\Actions\BuildGalleryZip closed
            // each archive volume — the split-at-3.9GiB boundary (#59), never
            // a byte range within one volume.
            $table->unsignedInteger('part_number');
            $table->string('key');
            $table->unsignedBigInteger('byte_size');

            $table->timestampsTz();

            $table->unique(['tenant_id', 'zip_build_id', 'part_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_build_parts');
    }
};
