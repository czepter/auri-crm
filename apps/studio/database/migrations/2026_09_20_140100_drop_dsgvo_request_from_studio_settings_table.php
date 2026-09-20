<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['dsgvo_request_recipient', 'dsgvo_request_deadline_days']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('dsgvo_request_recipient')->nullable();
            $table->unsignedSmallInteger('dsgvo_request_deadline_days')->default(30);
        });
    }
};
