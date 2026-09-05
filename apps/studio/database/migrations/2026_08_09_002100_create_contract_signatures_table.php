<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One signature, ever, per contract — the unique index on contract_id
        // is what makes "no unsign" a database fact rather than a convention:
        // there is no second row to represent a re-sign, and nothing in this
        // story deletes or updates the first.
        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // restrictOnDelete: a Contract that has been signed must stay put,
            // artifact and all — see App\Contracts\Models\Contract.
            $table->foreignId('contract_id')->constrained()->restrictOnDelete()->unique();

            // The acting contact — restrictOnDelete for the same reason: a
            // contact who has signed something is not one this app can drop.
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();

            $table->string('typed_name');
            $table->timestampTz('consented_at');

            // sha256(bytes actually read from Storage at the moment of
            // signing) — never a re-render. See App\Contracts\Actions\SignContract.
            $table->char('document_sha256', 64);

            // The ContractTemplate version the signed Contract was rendered
            // from, baked in at sign time. ContractTemplate rows are already
            // immutable per version (see ContractTemplate::newVersion()), so
            // this is redundant with contracts.contract_template_id today —
            // it exists so a signature reads as complete evidence on its own,
            // without a join through contracts and contract_templates.
            $table->unsignedInteger('contract_template_version');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};
