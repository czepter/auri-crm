<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            // Minor units (cents), same shape as Q-02's QuoteItem. Null is
            // "Enthaltene Leistung" — included in the package rather than
            // priced on its own — which is a property of the offering, not a
            // price a quote has to invent.
            $table->unsignedInteger('unit_price')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
