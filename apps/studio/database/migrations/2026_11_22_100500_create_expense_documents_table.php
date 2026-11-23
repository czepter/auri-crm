<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_documents', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('finance_documents')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['expense_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_documents');
    }
};
