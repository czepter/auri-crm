<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['form_fields', 'form_instance_fields'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('help', 500)->nullable()->after('required');
            });

            DB::table($table)->where('type', 'group_photos')->whereNotNull('options')->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $options = (array) json_decode($row->options, true);

                        if (! array_key_exists('help', $options)) {
                            continue;
                        }

                        $help = $options['help'];
                        unset($options['help']);

                        DB::table($table)->where('id', $row->id)->update([
                            'help' => is_string($help) && trim($help) !== '' ? $help : null,
                            'options' => json_encode($options),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('type', 'group_photos')->whereNotNull('help')->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $options = (array) json_decode($row->options ?? '[]', true);

                        DB::table($table)->where('id', $row->id)->update([
                            'options' => json_encode([...$options, 'help' => $row->help]),
                        ]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('help');
            });
        }
    }
};
