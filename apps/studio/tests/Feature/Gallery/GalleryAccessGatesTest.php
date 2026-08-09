<?php

use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('hashes the view password and never stores it in the clear', function () {
    $gallery = Gallery::factory()->create();

    $gallery->rotateViewPassword('basthorst2026');

    expect($gallery->requiresViewPassword())->toBeTrue()
        ->and($gallery->view_password_hash)->not->toBe('basthorst2026')
        ->and($gallery->checkViewPassword('basthorst2026'))->toBeTrue()
        ->and($gallery->checkViewPassword('wrong'))->toBeFalse();
});

it('hashes the download pin the same way', function () {
    $gallery = Gallery::factory()->create();

    $gallery->rotateDownloadPin('4821');

    expect($gallery->requiresDownloadPin())->toBeTrue()
        ->and($gallery->download_pin_hash)->not->toBe('4821')
        ->and($gallery->checkDownloadPin('4821'))->toBeTrue()
        ->and($gallery->checkDownloadPin('0000'))->toBeFalse();
});

it('clears a gate when rotated with null', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $gallery->rotateViewPassword(null);

    expect($gallery->requiresViewPassword())->toBeFalse()
        ->and($gallery->view_password_hash)->toBeNull();
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
