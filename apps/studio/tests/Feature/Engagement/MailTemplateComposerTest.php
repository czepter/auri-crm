<?php

use App\Crm\Models\ClientCase;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\PlainTextBody;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// bookedContactProject() and staffMember() live in tests/Pest.php.

function threadedProject(): ClientCase
{
    $project = bookedContactProject();
    Thread::factory()->for($project, 'case')->create();

    return $project;
}

// --- PlainTextBody: the editor writes HTML, the composer takes text ------

it('turns block boundaries into line breaks rather than running paragraphs together', function () {
    expect(PlainTextBody::fromHtml('<p>Hallo Lena,</p><p>anbei die Bilder.</p>'))
        ->toBe("Hallo Lena,\n\nanbei die Bilder.");
});

it('keeps list items on their own lines and decodes entities', function () {
    expect(PlainTextBody::fromHtml('<ul><li>Erstens</li><li>Zweitens &amp; drittens</li></ul>'))
        ->toBe("Erstens\nZweitens & drittens");
});

it('leaves a body that was already plain text alone', function () {
    expect(PlainTextBody::fromHtml('Nur Text.'))->toBe('Nur Text.');
});

// --- unknown merge fields ------------------------------------------------

it('names every merge path the vocabulary does not know', function () {
    expect(MergeFieldVocabulary::unknownPathsIn('{{ project.name }} und {{ project.nickname }}'))
        ->toBe(['project.nickname']);

    expect(MergeFieldVocabulary::unknownPathsIn('{{ contact.name }}'))->toBe([]);
});

// --- the composer --------------------------------------------------------

it('sends mail templates to the composer, resolved against this project', function () {
    $project = threadedProject();

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Mail,
        'name' => 'Erinnerung',
        'body' => '<p>Hallo {{ contact.name }},</p><p>bis bald.</p>',
    ]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertOk()
        ->assertInertia(function ($page) use ($project) {
            $template = $page->toArray()['props']['mailTemplates'][0];
            $name = $project->primaryContact()->name;

            expect($template['name'])->toBe('Erinnerung')
                ->and($template['body'])->toBe("Hallo {$name},\n\nbis bald.")
                ->and($template['body'])->not->toContain('<p>')
                ->and($template['unknownFields'])->toBe([]);
        });
});

it('marks a template that references a field the vocabulary does not have', function () {
    $project = threadedProject();

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Mail,
        'name' => 'Kaputt',
        'body' => '<p>Hallo {{ contact.nickname }}</p>',
    ]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertOk()
        ->assertInertia(function ($page) {
            expect($page->toArray()['props']['mailTemplates'][0]['unknownFields'])->toBe(['contact.nickname']);
        });
});

it('offers only mail templates, never a quote or an invoice one', function () {
    $project = threadedProject();

    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Mail, 'name' => 'Mail']);
    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Quote, 'name' => 'Angebot']);
    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Invoice, 'name' => 'Rechnung']);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/auftraege/{$project->public_id}/nachrichten")
        ->assertOk()
        ->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['mailTemplates'])->pluck('name');

            expect($names->all())->toBe(['Mail']);
        });
});

it('refuses to send a message that still carries a merge field', function () {
    $project = threadedProject();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/auftraege/{$project->public_id}/nachrichten", ['body' => 'Hallo {{ contact.name }}'])
        ->assertInvalid(['body']);

    expect($project->latestThread()->first()->messages()->count())->toBe(0);
});

it('sends an ordinary reply unchanged', function () {
    $project = threadedProject();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/auftraege/{$project->public_id}/nachrichten", ['body' => 'Hallo Lena, bis bald.'])
        ->assertRedirect();

    expect($project->latestThread()->first()->messages()->sole()->body)->toBe('Hallo Lena, bis bald.');
});
