<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * #520: the capability covers inquiries as well, so it names cases. Roles
 * hold it through the permission row, tokens as a string in their scopes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('view_all_projects', 'view_all_cases');
    }

    public function down(): void
    {
        $this->rename('view_all_cases', 'view_all_projects');
    }

    private function rename(string $from, string $to): void
    {
        DB::table(config('permission.table_names.permissions'))->where('name', $from)->update(['name' => $to]);

        DB::update(
            'update api_tokens set scopes = (select jsonb_agg(case when s = ? then ? else s end) from jsonb_array_elements_text(scopes) s) where scopes @> jsonb_build_array(?::text)',
            [$from, $to, $from],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
