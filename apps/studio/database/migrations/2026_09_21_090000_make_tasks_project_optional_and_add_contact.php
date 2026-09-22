<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::statement("alter table tasks add constraint tasks_client_needs_project check (audience <> 'client' or project_id is not null)");
    }

    public function down(): void
    {
        DB::statement('alter table tasks drop constraint tasks_client_needs_project');
        DB::table('tasks')->whereNull('project_id')->delete();

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
