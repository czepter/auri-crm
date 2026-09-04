<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('title');
            $table->text('description')->nullable();

            // Minor units, same meaning as in package_items: null is
            // "Enthaltene Leistung", not zero euros.
            $table->unsignedInteger('unit_price')->nullable();
            $table->timestampsTz();

            // The index is the rule itself: the tick on a quote line claims
            // "this title is in the library", and two rows with the same
            // title are the same claim.
            $table->unique(['tenant_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
