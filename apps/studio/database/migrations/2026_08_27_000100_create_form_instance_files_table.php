<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_instance_files', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('form_instance_field_id')->constrained()->cascadeOnDelete();

            // Private disk key. Never public — an answer file is client data
            // and is read back through a controller that authorises first,
            // the same as contracts and invoices.
            $table->string('path');
            $table->string('name');
            $table->string('mime');
            $table->unsignedBigInteger('size');

            // Who put it there, the same distinction form_answers records.
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            $table->index('form_instance_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_instance_files');
    }
};
