<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-09 (#228). T-08 gave the studio one welcome text, two legal links and
     * four switches — the same for every client. Sprint 25 built seven portal
     * screens whose behaviour appears nowhere in that pane.
     *
     * Two levels: a studio default, overridable per contact. Per CONTACT, not
     * per project: an event venue with forty dates a year should be set up
     * once, not forty times.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            // One text was never three states. P-03's inquiry, P-01's single
            // booking and P-02's venue with forty are different rooms, and a
            // sentence that fits one reads as a mistake in the others.
            // portal_welcome_text stays as the "one booking" case rather than
            // being renamed, so nothing a studio already wrote is lost.
            $table->text('portal_welcome_inquiry')->nullable();
            $table->text('portal_welcome_many')->nullable();

            // P-04 turned messaging from a boolean into three states: off, on
            // only inside a booking, or on including threads that name none.
            // The old boolean is migrated below and then dropped.
            $table->string('portal_messaging_mode')->default('off');

            // P-06's gallery defaults. These live per gallery today (the
            // `galleries` table) and nowhere as a default, so a studio sets
            // them again on every single delivery.
            $table->unsignedSmallInteger('gallery_default_expiry_days')->nullable();
            $table->boolean('gallery_default_requires_settled_balance')->default(false);
            $table->boolean('gallery_default_downloads_enabled')->default(true);
            $table->boolean('gallery_default_lists_enabled')->default(true);
            $table->boolean('gallery_default_comments_enabled')->default(true);

            // P-05 put subject access and erasure in the portal. Who hears
            // about one, and inside how long.
            $table->string('dsgvo_request_recipient')->nullable();
            $table->unsignedSmallInteger('dsgvo_request_deadline_days')->default(30);

            // The portal footer's contact line had no source at all —
            // portalBranding carries only logo, colour and display name.
            $table->string('portal_contact_phone')->nullable();
            $table->string('portal_contact_email')->nullable();
            $table->string('portal_contact_response_time')->nullable();
        });

        DB::table('studio_settings')->where('portal_messaging_enabled', true)
            ->update(['portal_messaging_mode' => 'booking']);

        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('portal_messaging_enabled'));

        Schema::table('contacts', function (Blueprint $table) {
            // One nullable JSON blob rather than a dozen nullable columns.
            // Every key is optional and an ABSENT key means inherit — which
            // is the distinction the whole feature turns on, and the one a
            // nullable boolean column would blur into "off". PortalSettings
            // is the only reader; nothing queries inside this.
            $table->jsonb('portal_overrides')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('portal_messaging_enabled')->default(false);
        });

        DB::table('studio_settings')->where('portal_messaging_mode', '!=', 'off')
            ->update(['portal_messaging_enabled' => true]);

        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn([
            'portal_welcome_inquiry', 'portal_welcome_many', 'portal_messaging_mode',
            'gallery_default_expiry_days', 'gallery_default_requires_settled_balance',
            'gallery_default_downloads_enabled', 'gallery_default_lists_enabled',
            'gallery_default_comments_enabled',
            'dsgvo_request_recipient', 'dsgvo_request_deadline_days',
            'portal_contact_phone', 'portal_contact_email', 'portal_contact_response_time',
        ]));

        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('portal_overrides'));
    }
};
