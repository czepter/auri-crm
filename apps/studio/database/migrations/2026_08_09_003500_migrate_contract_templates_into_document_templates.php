<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-12 replaces M-01's single-purpose contract_templates with the
 * generalised document_templates the previous migration created. No
 * production deploy exists for this repository yet (docs/documents.md,
 * docs/queue.md) — there is no real tenant data to preserve — but this still
 * copies rather than drops, because a dev database migrated forward from an
 * earlier story should not lose its templates.
 *
 * contracts.contract_template_id keeps its name (never renamed to
 * document_template_id): it names the document template one specific
 * Contract was rendered from, and "contract's template" reads correctly
 * whichever table backs it. Only the column's foreign key target moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['contract_template_id']);
        });

        DB::statement("
            INSERT INTO document_templates
                (public_id, tenant_id, family_id, version, type, shoot_kind, name, body, is_current, created_at, updated_at)
            SELECT public_id, tenant_id, family_id, version, 'contract', NULL, name, body, is_current, created_at, updated_at
            FROM contract_templates
        ");

        DB::statement('
            UPDATE contracts
            SET contract_template_id = document_templates.id
            FROM contract_templates, document_templates
            WHERE contracts.contract_template_id = contract_templates.id
              AND document_templates.public_id = contract_templates.public_id
        ');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('contract_template_id')->references('id')->on('document_templates')->restrictOnDelete();
        });

        Schema::dropIfExists('contract_templates');
    }

    public function down(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->char('family_id', 24);
            $table->unsignedInteger('version');
            $table->string('name');
            $table->longText('body');
            $table->boolean('is_current')->default(true);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'family_id', 'version']);
            $table->index(['tenant_id', 'family_id', 'is_current']);
        });

        DB::statement("
            INSERT INTO contract_templates
                (public_id, tenant_id, family_id, version, name, body, is_current, created_at, updated_at)
            SELECT public_id, tenant_id, family_id, version, name, body, is_current, created_at, updated_at
            FROM document_templates
            WHERE type = 'contract'
        ");

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['contract_template_id']);
        });

        DB::statement('
            UPDATE contracts
            SET contract_template_id = contract_templates.id
            FROM contract_templates, document_templates
            WHERE contracts.contract_template_id = document_templates.id
              AND contract_templates.public_id = document_templates.public_id
        ');

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('contract_template_id')->references('id')->on('contract_templates')->restrictOnDelete();
        });

        Schema::dropIfExists('document_templates');
    }
};
