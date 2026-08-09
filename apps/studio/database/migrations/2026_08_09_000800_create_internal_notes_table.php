<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Its own table, never a visibility flag on a client-visible message.
        // A boolean is one bad query away from a data breach; a separate table
        // makes the mistake structurally impossible instead of merely unlikely.
        Schema::create('internal_notes', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // Project-scoped, not shoot-scoped: the note that matters is about
            // the client and the arrangement.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notes');
    }
};
