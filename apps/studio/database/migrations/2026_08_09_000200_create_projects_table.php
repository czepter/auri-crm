<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');
            $table->string('type');

            // Defaults false so a raw inquiry cannot surface in a client portal
            // the moment it arrives (vision §3.1). Nothing reads it until P2;
            // the column exists now because adding it later would mean auditing
            // every project row created in the meantime.
            $table->boolean('portal_visible')->default(false);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
