<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who is expected to sign one Fassung. Not the evidence — that stays
        // in contract_signatures, which is append-only and unique per
        // contract. This row is the studio's own list, and signed_at is the
        // echo SignContract stamps on it.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signers');
    }
};
