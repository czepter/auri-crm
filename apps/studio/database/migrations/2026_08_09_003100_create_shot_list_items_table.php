<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shot_list_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('shoot_id')->constrained()->cascadeOnDelete();

            $table->string('label');

            // A timestamp, not a boolean, matching tasks.completed_at: when a
            // shot was checked off is as much the record as whether it was.
            $table->timestampTz('checked_off_at')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shot_list_items');
    }
};
