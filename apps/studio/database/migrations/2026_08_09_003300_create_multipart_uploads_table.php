<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multipart_uploads', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // The object's eventual quarantine location and the S3-issued
            // UploadId that names this multipart session there — see
            // App\Gallery\Actions\StartOrResumeMultipartUpload. Both are only
            // known once CreateMultipartUpload has actually run, but neither
            // is nullable: the row is only ever inserted after that call
            // returns, key and upload_id already in hand.
            $table->string('key');
            $table->string('upload_id');

            // The fingerprint (name, size, browser's File.lastModified) a
            // returning tab supplies to find this same row again — see #51's
            // own wording. Scoped to one gallery: two different galleries
            // coincidentally holding a same-named, same-sized file are not
            // the same upload.
            $table->string('filename');
            $table->unsignedBigInteger('file_size');
            $table->timestampTz('file_modified_at');

            // Fixed once at CreateMultipartUpload and never recomputed — a
            // resumed upload that re-chunked at a different size would
            // produce parts CompleteMultipartUpload can never reconcile.
            $table->unsignedInteger('part_size');

            $table->string('status')->default('in_progress');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('aborted_at')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'gallery_id']);
        });

        // A partial index, not $table->unique(): the fingerprint only has to
        // stay unique while a session is still resumable. A completed or
        // aborted row must never block a genuinely new upload of the same
        // file into the same gallery later — only one *in_progress* row per
        // fingerprint is the actual rule StartOrResumeMultipartUpload relies
        // on to find "the" session a returning tab should resume.
        DB::statement(<<<'SQL'
            create unique index multipart_uploads_fingerprint_unique
                on multipart_uploads (tenant_id, gallery_id, filename, file_size, file_modified_at)
                where status = 'in_progress'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('multipart_uploads');
    }
};
