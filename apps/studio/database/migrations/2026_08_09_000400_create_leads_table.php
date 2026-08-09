<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('stage')->default('new');

            // Stored, not derived. The board card's age is time in the CURRENT
            // stage — "8 Tage still" in Kontaktiert against "vor 2 Std." in Neu
            // — which cannot be computed without a transitions table. One
            // timestamp answers the only question asked of it; the full history
            // lives in audit_logs, which is already append-only.
            $table->timestampTz('stage_entered_at');

            // Lost is not a stage. Keeping it beside the stage preserves which
            // stage the lead died in, which is what the funnel is measured on.
            $table->timestampTz('lost_at')->nullable();
            $table->string('lost_reason')->nullable();

            $table->text('inquiry_text')->nullable();
            $table->string('captured_via');
            $table->foreignId('captured_by_id')->constrained('users');
            $table->timestampsTz();

            $table->index(['tenant_id', 'stage']);
            $table->index('lost_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
