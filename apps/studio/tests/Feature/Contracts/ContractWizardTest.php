<?php

use App\Contracts\Models\Contract;
use App\Contracts\Support\ClauseCatalog;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\MergeFieldVocabulary;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('writes the picked clauses as the draft wording, in the order the catalog gives them', function () {
    Queue::fake();

    $project = bookedContactProject();
    $template = DocumentTemplate::factory()->create();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/vertraege", [
            'template' => $template->public_id,
            'clauses' => [
                ['title' => 'Leistung', 'text' => 'Aufnahmen im Rahmen von {{ project.name }}.'],
                ['title' => 'Widerruf', 'text' => 'Vierzehn Tage.'],
            ],
        ])
        ->assertRedirect();

    $contract = Contract::sole();

    expect($contract->blocks['blocks'])->toHaveCount(2)
        ->and($contract->blocks['blocks'][0]['data']['title'])->toBe('Leistung')
        ->and($contract->body)->toContain('<section class="doc-clause"><h3>Leistung</h3>')
        ->and($contract->body)->toContain('Vierzehn Tage.');
});

it('leaves the template as the wording when no clause was picked', function () {
    Queue::fake();

    $project = bookedContactProject();
    $template = DocumentTemplate::factory()->create();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/vertraege", ['template' => $template->public_id]);

    expect(Contract::sole()->body)->toBeNull();
});

it('prefers a clause the studio wrote into its template over the catalog default', function () {
    $template = DocumentTemplate::factory()->create([
        'blocks' => ['blocks' => [
            ['type' => 'clause', 'data' => ['title' => 'Datenschutz', 'text' => 'Unser eigener Wortlaut.']],
        ]],
    ]);

    $clauses = collect(ClauseCatalog::forTemplate($template))->keyBy('key');

    expect($clauses['privacy']['text'])->toBe('Unser eigener Wortlaut.')
        ->and($clauses['scope']['text'])->toBe(collect(ClauseCatalog::all())->firstWhere('key', 'scope')['text']);
});

it('ships no clause with a merge field the vocabulary does not know', function () {
    $paths = MergeFieldVocabulary::paths();

    foreach (ClauseCatalog::all() as $clause) {
        preg_match_all('/\{\{\s*([\w.]+)\s*\}\}/', $clause['text'], $matches);

        foreach ($matches[1] as $path) {
            expect($paths)->toContain($path);
        }
    }
});
