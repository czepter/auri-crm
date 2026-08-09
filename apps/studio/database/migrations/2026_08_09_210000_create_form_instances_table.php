<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_instances', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Provenance only, snapshotted at seed time — never a foreign
            // key. FormInstance is a deep, disconnected copy of a
            // FormTemplate (see FormInstance's own doc comment), so it names
            // the template it started from without holding a reference a
            // later rename, edit or delete of that template could touch.
            $table->string('source_template_name')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_instances');
    }
};
