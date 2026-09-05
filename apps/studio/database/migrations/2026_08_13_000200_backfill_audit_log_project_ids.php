<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only the rows that already name a project can be placed. AcceptQuote
        // and SignContract wrote context['project'] long before this column
        // existed; everything else would be a guess, and a wrong entry in an
        // append-only trail cannot be corrected afterwards.
        DB::statement(<<<'SQL'
            update audit_logs a
               set project_id = p.id
              from projects p
             where a.project_id is null
               and a.context ->> 'project' = p.public_id
               and a.tenant_id = p.tenant_id
        SQL);
    }

    public function down(): void
    {
        // Deliberately empty. Reversing would clear correctly-placed rows
        // alongside the backfilled ones, and there is no marker distinguishing
        // the two.
    }
};
