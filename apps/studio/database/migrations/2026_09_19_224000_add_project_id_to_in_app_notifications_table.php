<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('in_app_notifications', function (Blueprint $table) {
            // cascadeOnDelete: the row's data->url points at the project's
            // own screen, which 404s the moment the project is gone.
            $table->foreignId('project_id')->nullable()->after('type')
                ->constrained('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
