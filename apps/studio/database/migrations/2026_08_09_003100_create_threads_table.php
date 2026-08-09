<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // E-06: the conversation itself. A project may have none yet — the
        // client is the one who opens the first — or several over its life;
        // nothing here assumes exactly one.
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
