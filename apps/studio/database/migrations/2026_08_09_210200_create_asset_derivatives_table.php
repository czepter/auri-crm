<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_derivatives', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            // The ladder per #52 (shaped by the P3 gallery spike): 400, 800,
            // 1600 and 2560 px, each as AVIF and WebP — clamped downward for
            // an original narrower than a given rung, never upscaled.
            $table->unsignedInteger('width');
            $table->string('format', 8);
            $table->string('key');
            $table->unsignedInteger('bytes');

            $table->timestampsTz();

            $table->unique(['tenant_id', 'asset_id', 'width', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_derivatives');
    }
};
