<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #520: the container is a case; "project" is left for the booked phase.
 * Renames only, so every row and public_id survives. Index and constraint
 * names keep their project_ spelling.
 */
return new class extends Migration
{
    private const CHILDREN = [
        'audit_logs', 'contracts', 'form_instances', 'galleries', 'in_app_notifications', 'internal_notes',
        'invoices', 'leads', 'payment_plans', 'project_contacts', 'quotes', 'shoots', 'tasks', 'threads',
    ];

    public function up(): void
    {
        Schema::rename('projects', 'cases');

        foreach (self::CHILDREN as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn('project_id', 'case_id'));
        }

        Schema::rename('project_contacts', 'case_contacts');

        // No morph map: the class name itself is stored.
        DB::table('automation_runs')->where('anchorable_type', 'App\Crm\Models\Project')
            ->update(['anchorable_type' => 'App\Crm\Models\ClientCase']);
    }

    public function down(): void
    {
        DB::table('automation_runs')->where('anchorable_type', 'App\Crm\Models\ClientCase')
            ->update(['anchorable_type' => 'App\Crm\Models\Project']);

        Schema::rename('case_contacts', 'project_contacts');

        foreach (self::CHILDREN as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn('case_id', 'project_id'));
        }

        Schema::rename('cases', 'projects');
    }
};
