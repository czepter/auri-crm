<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            update galleries g set project_type_id = pt.id
            from cases c join project_types pt on pt.tenant_id = c.tenant_id and pt.key = c.type
            where g.case_id = c.id and g.project_type_id is null
        SQL);
    }

    public function down(): void {}
};
