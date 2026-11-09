<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->string('invoice_mail_subject')->nullable();
            $blueprint->text('invoice_mail_body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['invoice_mail_subject', 'invoice_mail_body']);
        });
    }
};
