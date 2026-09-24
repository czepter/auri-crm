<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #520: an inquiry is addressed by its case's public_id. The lead's own id
 * stays only so old /anfragen links can still be resolved; new leads get none.
 * The trail's lead rows are re-keyed to the case, the id every reader of
 * lead.* events now looks up.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            update audit_logs a
               set subject = c.public_id
              from leads l
              join cases c on c.id = l.case_id
             where a.subject = l.public_id
               and a.tenant_id = l.tenant_id
               and a.event like 'lead.%'
        SQL);

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('public_id', 'legacy_public_id');
        });

        DB::statement('alter table leads alter column legacy_public_id drop not null');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update leads l
               set legacy_public_id = c.public_id
              from cases c
             where c.id = l.case_id
               and l.legacy_public_id is null
        SQL);

        DB::statement('alter table leads alter column legacy_public_id set not null');

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('legacy_public_id', 'public_id');
        });

        DB::statement(<<<'SQL'
            update audit_logs a
               set subject = l.public_id
              from leads l
              join cases c on c.id = l.case_id
             where a.subject = c.public_id
               and a.tenant_id = l.tenant_id
               and a.event like 'lead.%'
        SQL);
    }
};
