<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate(Capability::ManageFinances->value, 'staff');

        Role::findOrCreate(StaffRole::Admin->value, 'staff')->givePermissionTo($permission);
    }

    public function down(): void
    {
        Permission::query()->where('name', Capability::ManageFinances->value)->delete();
    }
};
