<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->json('retention_policy')->nullable();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->timestampTz('retention_applied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('retention_policy');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('retention_applied_at');
        });
    }
};
