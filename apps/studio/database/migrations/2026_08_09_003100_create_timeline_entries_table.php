<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // Per shoot, not per project: the wedding day's Ablauf is not the
            // engagement session's (E-04's own acceptance).
            $table->foreignId('shoot_id')->constrained()->cascadeOnDelete();

            // A clock reading at the venue, same shape as Shoot's call_time —
            // every entry in a day-of schedule is anchored to one.
            $table->time('time');
            $table->string('title');
            $table->string('location')->nullable();
            $table->string('note')->nullable();

            // A travel leg between two real stops, not a stop itself — same
            // row shape, rendered lighter because there is nowhere to be,
            // only somewhere to get to (mockup 04's .tlrow.move).
            $table->boolean('is_transit')->default(false);

            // Breaks a tie when two entries share a time, the same reason
            // Shoot carries one.
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_entries');
    }
};
