<?php

use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('is unguarded on all three gates by default', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->requiresViewPassword())->toBeFalse()
        ->and($gallery->requiresDownloadPin())->toBeFalse()
        ->and($gallery->requiresVerifiedEmail())->toBeFalse();
});

it('starts every version counter at one', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->view_password_version)->toBe(1)
        ->and($gallery->download_pin_version)->toBe(1)
        ->and($gallery->email_verification_version)->toBe(1);
});

it('gates on the view password and answers a wrong one with false', function () {
    $gallery = Gallery::factory()->create();

    $gallery->rotateViewPassword('basthorst2026');

    expect($gallery->requiresViewPassword())->toBeTrue()
        ->and($gallery->checkViewPassword('basthorst2026'))->toBeTrue()
        ->and($gallery->checkViewPassword('wrong'))->toBeFalse();
});

it('gates on the download pin the same way', function () {
    $gallery = Gallery::factory()->create();

    $gallery->rotateDownloadPin('4821');

    expect($gallery->requiresDownloadPin())->toBeTrue()
        ->and($gallery->checkDownloadPin('4821'))->toBeTrue()
        ->and($gallery->checkDownloadPin('0000'))->toBeFalse();
});

it('clears a gate when rotated with null', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $gallery->rotateViewPassword(null);

    expect($gallery->requiresViewPassword())->toBeFalse()
        ->and($gallery->view_password)->toBeNull();
});

it('bumps only the rotated gate\'s version', function () {
    $gallery = Gallery::factory()->create();

    $gallery->rotateViewPassword('one');
    $gallery->rotateDownloadPin('1234');
    $gallery->rotateEmailVerification(true);

    expect($gallery->view_password_version)->toBe(2)
        ->and($gallery->download_pin_version)->toBe(2)
        ->and($gallery->email_verification_version)->toBe(2);

    $gallery->rotateDownloadPin('5678');

    expect($gallery->fresh()->view_password_version)->toBe(2)
        ->and($gallery->fresh()->download_pin_version)->toBe(3)
        ->and($gallery->fresh()->email_verification_version)->toBe(2);
});

it('toggles the email requirement independently of the other two gates', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('one');
    $gallery->rotateDownloadPin('1234');

    $gallery->rotateEmailVerification(true);

    expect($gallery->fresh()->requiresVerifiedEmail())->toBeTrue()
        ->and($gallery->fresh()->view_password_version)->toBe(2)
        ->and($gallery->fresh()->download_pin_version)->toBe(2);

    $gallery->rotateEmailVerification(false);

    expect($gallery->fresh()->requiresVerifiedEmail())->toBeFalse()
        ->and($gallery->fresh()->email_verification_version)->toBe(3);
});

it('keeps the gate columns out of mass assignment', function () {
    $gallery = Gallery::factory()->create();

    $gallery->update(['view_password_hash' => 'whatever-i-want', 'requires_verified_email' => true]);
})->throws(\Illuminate\Database\Eloquent\MassAssignmentException::class);

// C-17 (#253): recoverable, because both values are handed to guests anyway
// and a studio that cannot look one up locks out everybody with the old one.
it('stores the view password so the studio can read it back, and still checks it', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $fresh = $gallery->fresh();

    expect($fresh->view_password)->toBe('basthorst2026')
        ->and($fresh->checkViewPassword('basthorst2026'))->toBeTrue()
        ->and($fresh->checkViewPassword('basthorst2025'))->toBeFalse()
        // Encrypted at rest: the column is not the value.
        ->and(DB::table('galleries')->where('id', $gallery->id)->value('view_password'))
        ->not->toBe('basthorst2026');
});

it('keeps a gate set before the change working, and clears the old hash on rotation', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['view_password_hash' => 'basthorst2026'])->save();

    expect($gallery->fresh()->requiresViewPassword())->toBeTrue()
        ->and($gallery->fresh()->checkViewPassword('basthorst2026'))->toBeTrue();

    $gallery->rotateViewPassword('winsen2027');

    expect($gallery->fresh()->view_password_hash)->toBeNull()
        ->and($gallery->fresh()->checkViewPassword('basthorst2026'))->toBeFalse();
});

it('leaves no gate standing when a rotation clears it', function () {
    $gallery = Gallery::factory()->create();
    $gallery->forceFill(['view_password_hash' => 'basthorst2026'])->save();

    $gallery->rotateViewPassword(null);

    expect($gallery->fresh()->requiresViewPassword())->toBeFalse();
});
