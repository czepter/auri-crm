<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // C-25: what a Hochzeit carries that a Portrait does not. `key` is
        // what projects.field_values holds, so it is fixed at creation for the
        // reason Stage::$key is — a rename may not orphan the stored values.
        Schema::create('project_fields', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('project_type_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');

            // select and tags only; null everywhere else.
            $table->jsonb('options')->nullable();
            // number only: "120" reads as "120 Personen".
            $table->string('unit')->nullable();

            $table->boolean('required')->default(false);
            $table->boolean('allow_new_options')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'project_type_id', 'key']);
            $table->index(['tenant_id', 'project_type_id', 'sort_order']);
        });

        // No value table: a project carries a handful of fields, they are
        // always read together, and the definition is the truth about the
        // type. A type change leaves this column standing.
        Schema::table('projects', function (Blueprint $table) {
            $table->jsonb('field_values')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('field_values');
        });

        Schema::dropIfExists('project_fields');
    }
};
