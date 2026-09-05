<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_contacts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestampsTz();

            $table->unique(['project_id', 'contact_id', 'role']);
        });

        // Not in the vision, added here: every screen in the system reads "the
        // client" off this pivot, and two primaries make that read ambiguous
        // with no error anywhere to notice it.
        DB::statement(
            "create unique index project_contacts_one_primary
             on project_contacts (project_id) where role = 'primary'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contacts');
    }
};
