<?php

use App\Contracts\Enums\ContractState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #441 reversed: a Vertrag is offered again, but nothing waits for one.
 * 2026_09_15_140000 dropped these three tables; this rebuilds them at the
 * shape they had then, minus the columns that only fed a gate — the lead
 * stage, the automation anchor and the payment-plan condition stay gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete: the version a contract was rendered from is
            // what a signature is evidence against.
            $table->foreignId('contract_template_id')->constrained('document_templates')->restrictOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users');

            $table->string('status')->default('pending');
            $table->string('artifact_path')->nullable();
            $table->timestampTz('rendered_at')->nullable();
            $table->text('error')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->string('state')->default(ContractState::Draft->value);
            $table->text('body')->nullable();
            $table->json('blocks')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->date('sign_deadline_on')->nullable();

            $table->timestampsTz();

            $table->index(['tenant_id', 'project_id']);
            $table->unique(['project_id', 'version']);
        });

        Schema::create('contract_signers', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();

            $table->timestampTz('signed_at')->nullable();
            $table->timestampTz('reminded_at')->nullable();

            $table->timestampsTz();

            $table->unique(['contract_id', 'contact_id']);
        });

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // One row, ever: no unsign, as a database fact.
            $table->foreignId('contract_id')->constrained()->restrictOnDelete()->unique();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();

            $table->string('typed_name');
            $table->timestampTz('consented_at');
            $table->char('document_sha256', 64);
            $table->unsignedInteger('contract_template_version');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestampsTz();
        });

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('portal_sign_contracts_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('portal_sign_contracts_enabled'));
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('contract_signers');
        Schema::dropIfExists('contracts');
    }
};
