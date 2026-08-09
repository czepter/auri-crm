<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('title');
            $table->string('audience');

            // Days relative to the shoot's own date. Negative is before it,
            // which is where almost every template sits — a briefing goes out
            // ahead of the day, not after. Signed on purpose: +14 is the
            // review request, and a separate "before/after" flag would make
            // every sort of these read a boolean first.
            $table->integer('day_offset');

            // Which occasions this applies to. Null means every shoot: a crew
            // briefing is owed for all of them, while "Familienliste" only
            // makes sense for a wedding.
            $table->string('shoot_kind')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
