<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_exports', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('kind');
            $table->foreignId('gallery_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('building');
            $table->text('error')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
        });

        // Ein Klick während des Baus startet keinen zweiten: je Art und
        // Galerie darf nur eine Zeile bauen.
        DB::statement("create unique index studio_exports_one_building on studio_exports (tenant_id, kind, (coalesce(gallery_id, 0))) where status = 'building'");
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_exports');
    }
};
