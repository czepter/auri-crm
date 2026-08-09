<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // The full lifecycle (App\Quotes\Enums\QuoteStatus) ships now, but
            // draft is the only value any code path in this story reaches —
            // sent/accepted/superseded/expired are Q-06/Q-07's transitions.
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('EUR');
            $table->date('valid_until')->nullable();

            // Minor units (cents). recalculateTotal() is the only writer —
            // see App\Quotes\Models\Quote — and sums each item's line total,
            // which is zero for an "Enthaltene Leistung" line.
            $table->unsignedInteger('total')->default(0);
            $table->timestampsTz();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
