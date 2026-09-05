<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_assignments', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // Per shoot, not per project: the second shooter on the wedding day
            // is not necessarily on the engagement session.
            $table->foreignId('shoot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('role');
            $table->timestampsTz();

            // One person holds one role on a shoot. Two rows for the same
            // person would make "who is on this day" a question with two
            // answers, and the avatars would render them twice.
            $table->unique(['shoot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_assignments');
    }
};
