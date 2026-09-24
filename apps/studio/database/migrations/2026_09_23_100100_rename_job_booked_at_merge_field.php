<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #519: job.bookedAt resolves to nothing once the booking lives on the
 * project. Every stored text MergeFieldResolver reads, not only the
 * templates: quotes, invoices and contracts carry their own edited copy.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'document_templates' => ['body', 'blocks'],
        'contracts' => ['body'],
        'quotes' => ['intro', 'outro'],
        'invoices' => ['intro', 'outro'],
        'automation_rules' => ['notification_subject', 'notification_body'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $cast = $column === 'blocks' ? '::jsonb' : '';

                DB::statement("update {$table} set {$column} = replace({$column}::text, 'job.bookedAt', 'project.bookedAt'){$cast} where {$column}::text like '%job.bookedAt%'");
            }
        }
    }

    public function down(): void {}
};
