<?php

namespace App\Settings\Support;

/**
 * Jede Tabelle mit tenant_id steht hier, im Export oder mit Grund nicht.
 * StudioExportTablesTest fällt um, sobald eine neue fehlt.
 */
final class StudioExportTables
{
    /** Tabelle => Datei im Archiv, in der ihre Daten stehen. */
    public const INCLUDED = [
        'assets' => 'Galerie-Archiv',
        'case_contacts' => 'projekte.csv',
        'cases' => 'projekte.csv',
        'contacts' => 'kontakte.csv',
        'credit_notes' => 'gutschriften/',
        'galleries' => 'Galerie-Archiv',
        'gallery_sets' => 'Galerie-Archiv',
        'invoice_items' => 'rechnungen/',
        'invoices' => 'rechnungen/',
        'leads' => 'projekte.csv',
        'messages' => 'nachrichten.json',
        'payment_installments' => 'rechnungen/',
        'quote_items' => 'angebote/',
        'quote_payment_stages' => 'angebote/',
        'quotes' => 'angebote/',
        'shoots' => 'projekte.csv',
        'stages' => 'projekte.csv',
        'threads' => 'nachrichten.json',
    ];

    /** Grund => Tabellen. */
    public const EXCLUDED = [
        'Zugangsdaten' => [
            'api_tokens', 'client_accesses', 'contact_email_changes', 'gallery_download_links',
            'gallery_email_verifications', 'oauth_clients', 'personal_access_tokens',
            'staff_invitations', 'user_email_changes', 'user_email_verifications', 'users',
        ],
        'Betriebsdaten' => [
            'audit_logs', 'automation_runs', 'document_views', 'download_events', 'gallery_grid_prefs', 'gallery_views',
            'idempotency_keys', 'in_app_notifications', 'multipart_uploads', 'notification_preferences',
            'quote_number_counters', 'recent_views', 'stripe_events', 'studio_exports',
            'zip_build_parts', 'zip_build_waiters', 'zip_builds',
        ],
        'Einstellungen und Vorlagen' => [
            'automation_rules', 'catalog_items', 'document_templates', 'form_fields', 'form_templates',
            'gallery_presets', 'number_ranges', 'package_items', 'package_payment_stages', 'packages',
            'project_types', 'studio_closures', 'studio_settings', 'task_templates',
        ],
        'Abgeleitet' => [
            'asset_derivatives', 'gallery_versions', 'inbound_messages',
        ],
        'Nicht im Umfang von #489' => [
            'absences', 'asset_comments', 'asset_favorites', 'contract_signatures', 'contract_signers',
            'contracts', 'crew_assignments', 'dsgvo_requests', 'form_answers', 'form_instance_fields',
            'form_instance_files', 'form_instances', 'gallery_guests', 'gallery_list_items', 'gallery_lists',
            'gallery_prerelease_signups', 'internal_notes', 'payment_plans', 'project_fields',
            'service_suggestions', 'shot_list_items', 'tasks', 'thread_participants', 'timeline_entries',
        ],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return [...array_keys(self::INCLUDED), ...array_merge(...array_values(self::EXCLUDED))];
    }
}
