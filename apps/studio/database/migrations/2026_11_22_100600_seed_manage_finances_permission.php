<?php

use App\Enums\StaffRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('manage_finances', 'staff');

        Role::findOrCreate(StaffRole::Admin->value, 'staff')->givePermissionTo($permission);
    }

    public function down(): void
    {
        Permission::query()->where('name', 'manage_finances')->delete();
    }
};
