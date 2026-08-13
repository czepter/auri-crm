<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // nullOnDelete, not cascade: the model throws on deleting, so a
            // cascade would ask the database to remove rows the application
            // refuses to remove.
            $table->foreignId('project_id')->nullable()->after('actor_id')
                ->constrained('projects')->nullOnDelete();

            $table->index(['tenant_id', 'project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'project_id', 'created_at']);
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
