<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Models\Contact;
use App\Models\User;
use App\Privacy\Actions\EraseContactDsgvo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function offeredPackage(string $name = 'Fotoalbum'): Package
{
    return Package::factory()->create(['name' => $name, 'portal_offered' => true]);
}

it('records a suggestion with the sentence that goes with it', function () {
    $user = User::factory()->withMfa()->create();
    $contact = Contact::factory()->create();
    $package = offeredPackage();

    $this->actingAs($user, 'staff')
        ->post("/kontakte/{$contact->public_id}/leistungen", [
            'package' => $package->public_id,
            'note' => 'Aus eurer Hochzeitsgalerie würde ich ein Album machen.',
        ])
        ->assertSessionHasNoErrors();

    $suggestion = ServiceSuggestion::query()->sole();

    expect($suggestion->contact_id)->toBe($contact->id)
        ->and($suggestion->package_id)->toBe($package->id)
        ->and($suggestion->note)->toContain('Album')
        ->and($suggestion->suggested_by_id)->toBe($user->id);
});

// Dasselbe ein zweites Mal vorzuschlagen ist keine zweite Aussage, sondern
// eine geänderte — deshalb updateOrCreate und der Unique-Index darunter.
it('rewrites the sentence instead of suggesting the same package twice', function () {
    $user = User::factory()->withMfa()->create();
    $contact = Contact::factory()->create();
    $package = offeredPackage();

    foreach (['Erster Satz.', 'Zweiter Satz.'] as $note) {
        $this->actingAs($user, 'staff')
            ->post("/kontakte/{$contact->public_id}/leistungen", ['package' => $package->public_id, 'note' => $note])
            ->assertSessionHasNoErrors();
    }

    expect(ServiceSuggestion::query()->count())->toBe(1)
        ->and(ServiceSuggestion::query()->sole()->note)->toBe('Zweiter Satz.');
});

it('refuses a package the studio does not offer in the portal', function () {
    $user = User::factory()->withMfa()->create();
    $contact = Contact::factory()->create();
    $package = Package::factory()->create(['portal_offered' => false]);

    $this->actingAs($user, 'staff')
        ->post("/kontakte/{$contact->public_id}/leistungen", ['package' => $package->public_id])
        ->assertSessionHasErrors('package');

    expect(ServiceSuggestion::query()->count())->toBe(0);
});

it('removes a suggestion', function () {
    $user = User::factory()->withMfa()->create();
    $contact = Contact::factory()->create();
    $suggestion = ServiceSuggestion::create([
        'contact_id' => $contact->id,
        'package_id' => offeredPackage()->id,
    ]);

    $this->actingAs($user, 'staff')
        ->delete("/kontakte/{$contact->public_id}/leistungen/{$suggestion->public_id}")
        ->assertSessionHasNoErrors();

    expect(ServiceSuggestion::query()->count())->toBe(0);
});

// Der Satz ist eine Notiz über einen Menschen. Eine Löschung, die ihn stehen
// lässt, ist keine.
it('takes the suggestion with it when the contact is erased', function () {
    $contact = Contact::factory()->create();
    ServiceSuggestion::create([
        'contact_id' => $contact->id,
        'package_id' => offeredPackage()->id,
        'note' => 'Ein Satz über diesen Kunden.',
    ]);

    app(EraseContactDsgvo::class)($contact, User::factory()->withMfa()->create());

    expect(ServiceSuggestion::query()->count())->toBe(0);
});
