<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One render of one exact template version for one project. The FK
        // to contract_templates names a specific row, never "whatever is
        // current" — that is what lets editing the template afterwards leave
        // every already-rendered contract, and its stored artifact, alone.
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete rather than cascade: a template version that
            // has been rendered for a project must stay put, per M-02's need
            // to record which version a signature was made against.
            $table->foreignId('contract_template_id')->constrained()->restrictOnDelete();

            $table->foreignId('requested_by_id')->nullable()->constrained('users');

            // pending | rendered | failed — App\Contracts\Enums\ContractStatus.
            // Starts pending the moment RenderContractJob is dispatched, never
            // before: the request that triggers a render returns immediately
            // and the screen reacts to this column changing on a later request.
            $table->string('status')->default('pending');

            // Where Storage keeps the PDF once RenderContractJob finishes.
            // Null until then. Every later read serves this file — nothing
            // ever re-renders on view.
            $table->string('artifact_path')->nullable();
            $table->timestampTz('rendered_at')->nullable();
            $table->text('error')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
