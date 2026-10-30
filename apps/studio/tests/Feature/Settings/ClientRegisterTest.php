<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Models\StudioSettings;
use App\Portal\Notifications\ClientAccessLink;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function useRegister(string $register): void
{
    StudioSettings::query()->update(['client_register' => $register]);
    Cache::store('redis')->flush();
}

it('writes the magic link formally by default', function () {
    Cache::store('redis')->flush();
    $contact = Contact::factory()->create();

    $message = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);

    expect($message->subject)->toBe('Ihr Zugang zum Kundenbereich')
        ->and($message->outroLines)->toContain('Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.');
});

it('writes the magic link with du under the informal register', function () {
    useRegister('informal');
    $contact = Contact::factory()->create();

    $message = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);

    expect($message->subject)->toBe('Dein Zugang zum Kundenbereich')
        ->and($message->outroLines)->toContain('Wenn du das nicht angefordert hast, kannst du diese E-Mail ignorieren.');
});

it('addresses a couple as ihr under the informal register', function () {
    useRegister('informal');
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Partner);
    $quote = Quote::factory()->for($project, 'case')->create();

    expect((new QuoteSent($quote))->toMail($contact)->subject)->toBe("Euer Angebot {$quote->number}");
});

it('hands the portal the register its strings are chosen by', function (string $register) {
    useSurface(Surface::PORTAL);
    useRegister($register);
    $contact = Contact::factory()->create();

    $props = $this->actingAs($contact, 'portal')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['clientRegister'])->toBe($register);
})->with(['formal', 'informal']);
