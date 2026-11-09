<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->string('quote_mail_subject')->nullable();
            $blueprint->text('quote_mail_body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['quote_mail_subject', 'quote_mail_body']);
        });
    }
};
