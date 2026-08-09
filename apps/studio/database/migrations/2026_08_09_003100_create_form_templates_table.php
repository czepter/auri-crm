<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');

            // Null applies to every project type, the same convention
            // TaskTemplate.shoot_kind uses: a template is not forced to pick
            // a type it does not care about.
            $table->string('project_type')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_templates');
    }
};
