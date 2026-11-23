<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->smallInteger('year');
            $table->string('vat_mode', 20)->default('standard');
            $table->timestampTz('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('snapshot')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'year']);
        });

        DB::statement("alter table fiscal_years add constraint fiscal_years_vat_mode_check check (vat_mode in ('standard', 'small_business'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
