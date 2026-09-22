<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['lead_type_presets', 'lead_source_presets', 'lead_channel_presets']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->jsonb('lead_type_presets')->default('[]');
            $table->jsonb('lead_source_presets')->default('[]');
            $table->jsonb('lead_channel_presets')->default('[]');
        });
    }
};
