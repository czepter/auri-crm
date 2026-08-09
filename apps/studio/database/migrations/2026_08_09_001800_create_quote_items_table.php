<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            // Minor units (cents). Null is "Enthaltene Leistung" — deliberately
            // not zero, since zero reads as free and null reads as already
            // paid for. No package_id here on purpose: a quote seeded from a
            // package (Package::copyItems()) carries no live link back to it,
            // so editing or deactivating the package afterwards touches
            // nothing already copied out of it.
            $table->unsignedInteger('unit_price')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['quote_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
