<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->string('reminder_mail_subject')->nullable();
            $blueprint->text('reminder_mail_body')->nullable();
            $blueprint->string('credit_note_mail_subject')->nullable();
            $blueprint->text('credit_note_mail_body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['reminder_mail_subject', 'reminder_mail_body', 'credit_note_mail_subject', 'credit_note_mail_body']);
        });
    }
};
