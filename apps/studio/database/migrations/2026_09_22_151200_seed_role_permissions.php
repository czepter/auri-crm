<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Capability::cases() as $capability) {
            Permission::findOrCreate($capability->value, 'staff');
        }

        foreach (StaffRole::cases() as $staffRole) {
            $role = Role::findOrCreate($staffRole->value, 'staff');
            $role->syncPermissions(array_map(
                fn (Capability $capability) => $capability->value,
                $staffRole->capabilities(),
            ));
        }

        User::query()->each(function (User $user) {
            $user->syncRoles([$user->role->value]);
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        DB::table($tableNames['model_has_roles'])->where('model_type', User::class)->delete();
        DB::table($tableNames['role_has_permissions'])->delete();
        Role::query()->whereIn('name', array_map(fn (StaffRole $r) => $r->value, StaffRole::cases()))->delete();
        Permission::query()->whereIn('name', array_map(fn (Capability $c) => $c->value, Capability::cases()))->delete();
    }
};
