<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_instance_fields', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('form_instance_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('type');
            $table->boolean('required')->default(false);

            // Same convention as form_fields.options: only the "select" type
            // reads this.
            $table->json('options')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['form_instance_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_instance_fields');
    }
};
