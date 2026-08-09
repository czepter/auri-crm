<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-06: who a tenant's outgoing mail leaves under, and what it says at the
 * bottom. All nullable — StudioSettingsResolver::mailFromName()/
 * mailFromAddress() fall back to config('mail.from.*') exactly the way
 * timezone()/locale() fall back to config('app.*'), so a studio that has not
 * opened this pane yet sees exactly what it saw before this story existed.
 *
 * No footer column: the footer is T-02's legal particulars
 * (StudioSettingsResolver::attributes()), composed at send time from the
 * same columns RenderContractJob::mergeData() already reads — a second copy
 * here is exactly the drift T-06's acceptance criterion rules out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();

            // Where replies should land, only when that differs from the
            // sender — null leaves a reply going to mail_from_address/the
            // mailer default, same as today.
            $table->string('mail_reply_to')->nullable();

            // Appended as its own line, after the notification's own body
            // and before the legal footer.
            $table->text('mail_signature')->nullable();

            // Studios that keep correspondence in their own inbox — null
            // leaves outgoing mail unBCC'd.
            $table->string('mail_bcc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['mail_from_name', 'mail_from_address', 'mail_reply_to', 'mail_signature', 'mail_bcc']);
        });
    }
};
