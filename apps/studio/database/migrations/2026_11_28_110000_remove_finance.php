<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Finanzen module goes. No archive of the rows; stored receipts are not
 * touched here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('expense_documents');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('finance_documents');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('fiscal_years');

        Permission::query()->where('name', 'manage_finances')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
