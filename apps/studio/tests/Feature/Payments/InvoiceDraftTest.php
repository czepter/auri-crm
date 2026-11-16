<?php

use App\Enums\StaffRole;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Models\NumberCounter;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceDraft;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
});

it('keeps a half-filled form as a draft without drawing a number, and drops it once issued', function () {
    Storage::fake('local');
    Bus::fake();

    $admin = $this->actingAs(staffMember(StaffRole::Admin), 'staff');

    $admin->post('/rechnungen/entwuerfe', ['contact' => ['name' => 'Jonas Gast'], 'items' => [['title' => '']]])
        ->assertRedirect('/rechnungen/entwuerfe/'.InvoiceDraft::sole()->public_id);

    expect(Invoice::count())->toBe(0)
        ->and(NumberCounter::query()->where('document_type', DocumentType::Invoice->value)->exists())->toBeFalse();

    $draft = InvoiceDraft::sole();
    $admin->put("/rechnungen/entwuerfe/{$draft->public_id}", ['contact' => ['name' => 'Jonas Gast'], 'items' => [['title' => 'Abzüge', 'unit_price' => 2_000]]])->assertRedirect();
    expect($draft->fresh()->payload['items'][0]['title'])->toBe('Abzüge');

    $props = $admin->get("/rechnungen/entwuerfe/{$draft->public_id}")->assertOk()->viewData('page')['props'];
    expect($props['draft']['id'])->toBe($draft->public_id);

    $admin->post('/rechnungen', [
        'draft_id' => $draft->public_id,
        'contact' => ['name' => 'Jonas Gast'],
        'due_on' => '2026-12-01',
        'items' => [['title' => 'Abzüge', 'quantity' => 1, 'unit_price' => 2_000]],
    ])->assertRedirect();

    expect(Invoice::count())->toBe(1)->and(InvoiceDraft::count())->toBe(0);
});
