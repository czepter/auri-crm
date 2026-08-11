<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_answers', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('form_instance_field_id')->constrained()->cascadeOnDelete();

            // Starts at 1 and only ever goes up per field — FormInstanceField
            // ::answer() reads max(version)+1 inside a transaction, the same
            // shape Gallery::publish() and DocumentTemplate::newVersion() use
            // for their own families.
            $table->unsignedInteger('version');

            // Never updated after creation — editing an answer appends a new
            // row rather than touching this one, so a version already read
            // keeps its exact original value and created_at (E-03's
            // acceptance criterion) no matter how many times the field is
            // answered again afterwards.
            $table->text('value')->nullable();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'form_instance_field_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_answers');
    }
};
