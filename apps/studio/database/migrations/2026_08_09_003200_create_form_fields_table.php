<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('form_template_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('type');
            $table->boolean('required')->default(false);

            // Only the "select" type reads this — the list of choices staff
            // typed in. Every other type leaves it null rather than the
            // builder inventing per-type columns nothing else uses.
            $table->json('options')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['form_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
