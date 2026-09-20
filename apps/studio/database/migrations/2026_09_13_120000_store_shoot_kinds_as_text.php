<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LABELS = [
        'engagement' => 'Verlobungsshooting',
        'wedding' => 'Hochzeitstag',
        'reception' => 'Feier',
        'portrait' => 'Portrait',
        'other' => 'Sonstige',
    ];

    public function up(): void
    {
        $this->rename(self::LABELS);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::LABELS));
    }

    /** @param  array<string, string>  $map */
    private function rename(array $map): void
    {
        foreach (['shoots' => 'kind', 'task_templates' => 'shoot_kind', 'document_templates' => 'shoot_kind'] as $table => $column) {
            foreach ($map as $from => $to) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
