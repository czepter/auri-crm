<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-08: the client portal's own settings. Two unrelated concerns share this
 * table only because every tenant-scoped setting does (T-01) — a maintenance
 * closure and four capability switches.
 *
 * `portal_maintenance_enabled`/`_message` are a temporary-closure switch, not
 * a third revocation path (A-04 already drew that line: hide-project vs
 * revoke-contact, never a global one). The middleware that reads this never
 * touches `project_contacts` or `contacts.portal_access_revoked_at`.
 *
 * The four `portal_*_enabled` columns are capability switches, each
 * defaulting to OFF regardless of whether the feature behind it already
 * ships elsewhere (M-02's signing, T-07's payment screen) — a studio opts
 * the portal into a capability, the capability existing is not itself
 * consent. `portal_accept_quotes_enabled` and `portal_messaging_enabled`
 * have nothing to gate yet (Q-07, E-06); they exist so a later story wires a
 * route in front of an already-off switch rather than adding one.
 *
 * `portal_welcome_text` is the portal home's own copy, separate from
 * `display_name`/`accent_color` (T-02) which already serve the whole portal,
 * not just the home screen. `portal_legal_datenschutz_url`/`_agb_url` are
 * plain external links — no CMS, no local page, matching the issue's own
 * "a simple settings-configured URL field for each is enough". The Impressum
 * has no column of its own: it renders straight from T-02's existing legal
 * fields (StudioSettings::profile()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('portal_maintenance_enabled')->default(false);
            $table->text('portal_maintenance_message')->nullable();

            $table->text('portal_welcome_text')->nullable();
            $table->string('portal_legal_datenschutz_url')->nullable();
            $table->string('portal_legal_agb_url')->nullable();

            $table->boolean('portal_accept_quotes_enabled')->default(false);
            $table->boolean('portal_sign_contracts_enabled')->default(false);
            $table->boolean('portal_payment_status_enabled')->default(false);
            $table->boolean('portal_messaging_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn([
                'portal_maintenance_enabled', 'portal_maintenance_message',
                'portal_welcome_text', 'portal_legal_datenschutz_url', 'portal_legal_agb_url',
                'portal_accept_quotes_enabled', 'portal_sign_contracts_enabled',
                'portal_payment_status_enabled', 'portal_messaging_enabled',
            ]);
        });
    }
};
