<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_number_counters', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            // No publicId() here — unlike every other table in this schema,
            // a counter row is never looked up by route or API, only ever
            // summed by AllocateQuoteNumber. Nothing external ever holds a
            // reference to one, so it has no identity worth hiding.
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('counter');

            $table->timestampsTz();

            // The actual safety net: two allocations racing for the same
            // slot both compute the same next counter, and this index turns
            // the loser's insert into a constraint violation rather than a
            // silently duplicated AN-{year}-{counter}.
            $table->unique(['tenant_id', 'year', 'counter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_number_counters');
    }
};
