<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Der Kunde bekommt dieselben drei Zustände wie das Studio. Was für ihn
 * geschrieben wurde, galt bisher für jeden davon — es zieht deshalb in alle
 * drei Schlüssel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            update contacts
               set portal_overrides = (portal_overrides - 'welcome')
                   || jsonb_build_object(
                        'welcome_inquiry', portal_overrides -> 'welcome',
                        'welcome_active', portal_overrides -> 'welcome',
                        'welcome_done', portal_overrides -> 'welcome'
                      )
             where jsonb_exists(portal_overrides, 'welcome')
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update contacts
               set portal_overrides = (portal_overrides - 'welcome_inquiry' - 'welcome_active' - 'welcome_done')
                   || jsonb_build_object('welcome', portal_overrides -> 'welcome_active')
             where jsonb_exists(portal_overrides, 'welcome_active')
        SQL);
    }
};
