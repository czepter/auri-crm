<?php

use App\Crm\Models\Project;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function letterOwner(): User
{
    return User::factory()->withMfa()->create();
}

/** The one HTML Gotenberg was asked to convert. */
function renderedHtml(): string
{
    $body = '';

    Http::assertSent(function ($request) use (&$body) {
        $body = (string) $request->body();

        return true;
    });

    return $body;
}

// --- Anschreiben ------------------------------------------------------

it('stores the letter through the quote patch', function () {
    $quote = Quote::factory()->create();

    $this->actingAs(letterOwner(), 'staff')
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'classic',
            'intro' => 'Hallo {{ contact.name }},',
            'outro' => 'Bis bald.',
            'items' => [],
        ])
        ->assertRedirect();

    expect($quote->fresh())->intro->toBe('Hallo {{ contact.name }},')->outro->toBe('Bis bald.');
});

it('renders the letter around the positions, merge fields resolved', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-quote', 200)]);

    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Quote, 'body' => '<p>Vorlage</p>']);

    $quote = Quote::factory()->create([
        'intro' => 'Guten Tag {{ contact.name }},',
        'outro' => 'Herzlich',
    ]);
    QuoteItem::factory()->for($quote)->create(['title' => 'Reportage', 'quantity' => 1, 'unit_price' => 290000]);
    $quote->recalculateTotal();

    $this->actingAs(letterOwner(), 'staff')
        ->get("/angebote/{$quote->public_id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $html = renderedHtml();

    expect($html)->toContain('Reportage')
        ->and($html)->toContain('2.900,00')
        ->and(strpos($html, 'Guten Tag'))->toBeLessThan(strpos($html, 'Reportage'))
        ->and(strpos($html, 'Herzlich'))->toBeGreaterThan(strpos($html, 'Reportage'))
        ->and($html)->not->toContain('{{');
});

it('escapes what the letter carries rather than rendering it', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-quote', 200)]);

    $quote = Quote::factory()->create(['intro' => '<script>alert(1)</script>']);

    $this->actingAs(letterOwner(), 'staff')->get("/angebote/{$quote->public_id}/pdf")->assertOk();

    expect(renderedHtml())->not->toContain('<script>');
});

// --- Nachtrag ---------------------------------------------------------

it('creates an addendum as its own quote, leaving the accepted one accepted', function () {
    $project = Project::factory()->create();
    $accepted = Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted]);

    $this->actingAs(letterOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['addendum_to' => $accepted->public_id])
        ->assertRedirect();

    $addendum = $project->quotes()->where('is_addendum', true)->sole();

    expect($addendum->predecessor_id)->toBe($accepted->id)
        ->and($addendum->status)->toBe(QuoteStatus::Draft)
        ->and($accepted->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('refuses an addendum to a quote that was never accepted', function () {
    $project = Project::factory()->create();
    $sent = Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent]);

    $this->actingAs(letterOwner(), 'staff')
        ->post("/auftraege/{$project->public_id}/angebote", ['addendum_to' => $sent->public_id])
        ->assertNotFound();
});
