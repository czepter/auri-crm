<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zip_builds', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_version_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->string('size');

            $table->string('status')->default('building');
            $table->text('error')->nullable();

            $table->timestampsTz();

            // Dedupe, per #59: a second request for the same (version, scope,
            // size) reuses the row a first request already created rather
            // than building a second archive — App\Media\Actions\
            // RequestZipBuild finds-or-creates on exactly this index. Not
            // partial: a Failed row still occupies its triple, retried by
            // flipping this same row back to Building rather than inserting
            // a duplicate.
            $table->unique(['tenant_id', 'gallery_version_id', 'scope', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_builds');
    }
};
