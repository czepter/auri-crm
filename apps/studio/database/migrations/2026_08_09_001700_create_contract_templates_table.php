<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // Groups every version of "the same template" together. Stable
            // across edits — see ContractTemplate::newVersion() — while
            // public_id stays version-specific, because M-02 records which
            // version a signature was made against and that has to name one
            // exact row, never a family.
            $table->char('family_id', 24);

            // Starts at 1 and only ever goes up per family — ContractTemplate::
            // newVersion() reads max(version)+1 inside a transaction, the same
            // shape AllocateQuoteNumber uses for AN-{year}-{counter}.
            $table->unsignedInteger('version');

            $table->string('name');

            // The merge-field syntax `{{ project.name }}` is documented in
            // docs/contracts.md and resolved by MergeFieldResolver — never
            // here, and never by a controller. See RenderContractJob.
            $table->longText('body');

            // Which row in the family is "the template" right now. Editing
            // moves this pointer to a new row rather than mutating the old
            // one, so a Contract already rendered from an older version keeps
            // pointing at a row that never changes under it.
            $table->boolean('is_current')->default(true);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'family_id', 'version']);
            $table->index(['tenant_id', 'family_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
