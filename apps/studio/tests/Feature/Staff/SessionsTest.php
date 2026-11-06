<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function sessionRow(int $userId, string $id, bool $staff = true): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0 (Macintosh) Firefox/130.0',
        'payload' => base64_encode(serialize([($staff ? Auth::guard('staff')->getName() : 'login_portal_x') => $userId])),
        'last_activity' => now()->timestamp,
    ]);
}

function sessionIds(): array
{
    return DB::table('sessions')->orderBy('id')->pluck('id')->all();
}

it('lists the own staff sessions and nobody else\'s', function () {
    $user = staffMember();
    $other = staffMember();

    sessionRow($user->id, 'mine');
    sessionRow($user->id, 'portal-with-the-same-number', staff: false);
    sessionRow($other->id, 'theirs');

    $sessions = $this->actingAs($user, 'staff')
        ->get('/profil/sicherheit')
        ->assertOk()
        ->viewData('page')['props']['sessions'];

    expect(array_column($sessions, 'id'))->toBe(['mine'])
        ->and($sessions[0]['current'])->toBeFalse();
});

it('groups other sessions by device and ends the group at once', function () {
    $user = staffMember();

    foreach (['a', 'b', 'c'] as $id) {
        sessionRow($user->id, $id);
    }

    $sessions = $this->actingAs($user, 'staff')
        ->get('/profil/sicherheit')
        ->viewData('page')['props']['sessions'];

    expect($sessions)->toHaveCount(1)
        ->and($sessions[0]['device'])->toBe('Firefox')
        ->and($sessions[0]['count'])->toBe(3);

    $this->delete('/profil/sitzungen/a', ['ids' => $sessions[0]['ids']])->assertRedirect();

    expect(sessionIds())->toBe([]);
});

it('fills sessions.user_id for the staff guard', function () {
    config(['session.driver' => 'database']);

    $user = staffMember();

    $this->actingAs($user, 'staff')->get('/profil')->assertOk();

    expect(DB::table('sessions')->value('user_id'))->toBe($user->id);
});

it('ends one session and audits it', function () {
    $user = staffMember();
    sessionRow($user->id, 'a');
    sessionRow($user->id, 'b');

    $this->actingAs($user, 'staff')->delete('/profil/sitzungen/a')->assertRedirect();

    expect(sessionIds())->toBe(['b'])
        ->and(AuditLog::where('event', 'profile.session_revoked')->count())->toBe(1);
});

it('does not end a session that is not the own or is the current one', function () {
    $user = staffMember();
    $other = staffMember();
    sessionRow($other->id, 'theirs');

    $current = str_repeat('c', 40);
    sessionRow($user->id, $current);

    $this->actingAs($user, 'staff')->withCookie(config('session.cookie'), $current);
    $this->delete('/profil/sitzungen/theirs')->assertNotFound();
    $this->delete("/profil/sitzungen/{$current}")->assertNotFound();

    expect(sessionIds())->toContain('theirs', $current);
});

it('ends all other sessions with the password and keeps the current one', function () {
    $user = User::factory()->withMfa()->create(['password' => Hash::make('correct-horse')]);
    $other = staffMember();

    $current = str_repeat('c', 40);
    sessionRow($user->id, $current);
    sessionRow($user->id, 'a');
    sessionRow($user->id, 'b');
    sessionRow($other->id, 'theirs');

    $this->actingAs($user, 'staff')->withCookie(config('session.cookie'), $current);

    $this->delete('/profil/sitzungen', ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');

    expect(sessionIds())->toHaveCount(4);

    $this->delete('/profil/sitzungen', ['current_password' => 'correct-horse'])->assertRedirect();

    expect(sessionIds())->toBe([$current, 'theirs']);
});
