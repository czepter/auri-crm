<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // T-12 generalises M-01's contract_templates into one versioned model
        // for every document type — see the next migration for how existing
        // contract templates land here, and App\Documents\Models\
        // DocumentTemplate for the versioning rules this table exists to
        // support (family_id/version/is_current, unchanged from M-01).
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->char('family_id', 24);
            $table->unsignedInteger('version');

            // contract | quote | mail — App\Documents\Enums\DocumentTemplateType.
            // Stable across a family's versions, same as name/body are free to
            // change but the family's identity is not.
            $table->string('type');

            // A variant for one App\Crm\Enums\ShootKind, or null for "every
            // kind" — a template family that needs different wording per
            // shoot kind gets its own row per kind rather than one template
            // full of conditionals (T-12's own acceptance criterion).
            $table->string('shoot_kind')->nullable();

            $table->string('name');

            // {{ path.to.field }} merge fields, resolved by App\Documents\
            // Support\MergeFieldResolver against App\Documents\Support\
            // DocumentMergeDataBuilder's array — never here, never by a
            // controller. See docs/documents.md.
            $table->longText('body');

            $table->boolean('is_current')->default(true);

            $table->timestampsTz();

            $table->unique(['tenant_id', 'family_id', 'version']);
            $table->index(['tenant_id', 'type', 'shoot_kind', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
