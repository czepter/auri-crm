<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_versions', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // Starts at 1 and only ever goes up per gallery — Gallery::publish()
            // reads max(version)+1 inside a transaction, the same shape
            // DocumentTemplate::newVersion() uses for its own family.
            $table->unsignedInteger('version');

            // A snapshot of Gallery's draft columns at the moment publish()
            // ran — never updated afterwards. G-08 relies on that: a
            // version-scoped cache path is only ever correct if the row it
            // names never changes under it.
            $table->string('title')->nullable();
            $table->json('assets');

            $table->timestampTz('published_at');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_versions');
    }
};
