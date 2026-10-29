<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // Set even on a Failed row — App\Gallery\Actions\IngestCompletedUpload
            // computes this before anything else can go wrong, and it is the
            // one thing every re-run of the same bytes has in common. Nullable
            // only for the rare case the download itself never completed.
            $table->char('checksum', 64)->nullable();

            $table->string('status');
            $table->text('error')->nullable();

            // The final, gallery-scoped object key once promoted out of
            // quarantine (G-02) — null while status is failed, since a failed
            // asset is never moved out of quarantine.
            $table->string('key')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('byte_size')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestampTz('taken_at')->nullable();
            $table->string('camera_make')->nullable();
            $table->string('camera_model')->nullable();

            // 16 hex chars — a 64-bit dct-based perceptual hash. See
            // App\Media\Support\PerceptualHash.
            $table->string('phash', 16)->nullable();
            $table->string('blurhash')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'gallery_id']);
        });

        // Idempotency, per #52: the same bytes reprocessed (a retry, a second
        // upload of the same file) must converge on one asset row rather than
        // create a duplicate — whether that row ended up Ready or Failed.
        // Partial, not $table->unique(): a null checksum (the download itself
        // never completed) must never collide with another null.
        DB::statement(<<<'SQL'
            create unique index assets_gallery_checksum_unique
                on assets (tenant_id, gallery_id, checksum)
                where checksum is not null
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
