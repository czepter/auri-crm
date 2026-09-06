<?php

use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvited;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders an unmodified notification through the compiled layout without error', function () {
    $owner = User::factory()->create(['role' => StaffRole::Owner]);
    $invitation = StaffInvitation::create([
        'email' => 'nina@nordlicht.test',
        'name' => 'Nina Sturm',
        'role' => StaffRole::SecondShooter,
        'token_hash' => StaffInvitation::hash('raw-token'),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => $owner->id,
    ])->fresh();

    $html = (new StaffInvited($invitation, 'https://example.test/einladung/abc', app(StudioSettingsResolver::class)))
        ->toMail($owner)
        ->render();

    expect((string) $html)
        ->toContain('Zugang einrichten')
        ->and((string) $html)->not->toBeEmpty();
});
