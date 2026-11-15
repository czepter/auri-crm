<?php

use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('moves a group photo question\'s options.help into the help column and back', function () {
    $migration = require database_path('migrations/2026_11_15_100000_add_help_to_form_fields.php');
    $instance = FormInstance::factory()->create();
    $withHelp = FormInstanceField::factory()->for($instance, 'formInstance')->create([
        'type' => 'group_photos', 'options' => ['suggestions' => ['Eltern'], 'minutesPerGroup' => 3],
    ]);
    $blank = FormInstanceField::factory()->for($instance, 'formInstance')->create([
        'type' => 'group_photos', 'options' => ['suggestions' => []],
    ]);

    $migration->down();
    DB::table('form_instance_fields')->where('id', $withHelp->id)->update(['options' => json_encode(['help' => 'Wählt die Gruppen.', 'suggestions' => ['Eltern'], 'minutesPerGroup' => 3])]);
    DB::table('form_instance_fields')->where('id', $blank->id)->update(['options' => json_encode(['help' => '', 'suggestions' => []])]);

    $migration->up();

    $row = DB::table('form_instance_fields')->where('id', $withHelp->id)->first();
    expect($row->help)->toBe('Wählt die Gruppen.')
        ->and(json_decode($row->options, true))->toBe(['suggestions' => ['Eltern'], 'minutesPerGroup' => 3])
        ->and(DB::table('form_instance_fields')->where('id', $blank->id)->value('help'))->toBeNull()
        ->and(json_decode(DB::table('form_instance_fields')->where('id', $blank->id)->value('options'), true))->toBe(['suggestions' => []]);

    $migration->down();
    $migration->up();
    expect(DB::table('form_instance_fields')->where('id', $withHelp->id)->value('help'))->toBe('Wählt die Gruppen.');
});
