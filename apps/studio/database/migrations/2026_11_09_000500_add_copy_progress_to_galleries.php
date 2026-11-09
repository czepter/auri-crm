<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('copy_source_id')->nullable()->constrained('galleries')->nullOnDelete();
            $table->unsignedInteger('copy_total')->nullable();
            $table->jsonb('copy_asset_ids')->nullable();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('copied_from_asset_id')->nullable()->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropConstrainedForeignId('copied_from_asset_id'));
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copy_source_id');
            $table->dropColumn(['copy_total', 'copy_asset_ids']);
        });
    }
};
