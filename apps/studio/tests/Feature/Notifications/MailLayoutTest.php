<?php

use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Models\StudioSettings;
use App\Models\User;
use App\Staff\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('renders an unmodified notification through the compiled layout without error', function () {
    $owner = User::factory()->create(['role' => StaffRole::Admin]);
    $invitation = StaffInvitation::create([
        'email' => 'nina@nordlicht.test',
        'name' => 'Nina Sturm',
        'role' => StaffRole::Contributor,
        'token_hash' => StaffInvitation::hash('raw-token'),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => $owner->id,
    ])->fresh();

    $html = (new StaffInvited($invitation, 'https://example.test/einladung/abc'))
        ->toMail($owner)
        ->render();

    expect((string) $html)
        ->toContain('Zugang einrichten')
        ->and((string) $html)->not->toBeEmpty();
});

it('carries the studio accent color into the rendered button', function () {
    StudioSettings::query()->update(['accent_color' => '#ab12cd']);
    Cache::store('redis')->flush();

    $owner = User::factory()->create(['role' => StaffRole::Admin]);
    $invitation = StaffInvitation::create([
        'email' => 'nina@nordlicht.test',
        'name' => 'Nina Sturm',
        'role' => StaffRole::Contributor,
        'token_hash' => StaffInvitation::hash('raw-token'),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => $owner->id,
    ])->fresh();

    $html = (new StaffInvited($invitation, 'https://example.test/einladung/abc'))
        ->toMail($owner)
        ->render();

    expect((string) $html)->toContain('#ab12cd');
});
