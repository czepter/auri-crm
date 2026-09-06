<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Who owes it, next to completed_by_id which records who paid it.
            // nullOnDelete rather than cascade: a task loses its owner when
            // somebody leaves the studio, it does not stop existing.
            $table->foreignId('assignee_id')->nullable()->after('completed_by_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });
    }
};
