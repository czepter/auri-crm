<?php

use App\Gallery\Models\Gallery;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    $gallery->update(['view_password' => 'whatever-i-want', 'requires_verified_email' => true]);
})->throws(MassAssignmentException::class);

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

it('lets the old secret go the moment a new one is set', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $gallery->rotateViewPassword('winsen2027');

    expect($gallery->fresh()->checkViewPassword('basthorst2026'))->toBeFalse()
        ->and($gallery->fresh()->checkViewPassword('winsen2027'))->toBeTrue();
});

it('leaves no gate standing when a rotation clears it', function () {
    $gallery = Gallery::factory()->create();
    $gallery->rotateViewPassword('basthorst2026');

    $gallery->rotateViewPassword(null);

    expect($gallery->fresh()->requiresViewPassword())->toBeFalse();
});

// Ohne Bestandsdaten hält das Argument nicht mehr, das die Hash-Spalten
// gehalten hat — sie waren der Rückfallpfad für Galerien, die vor C-17 ein
// Tor bekamen.
it('carries no legacy hash columns', function () {
    expect(Schema::hasColumn('galleries', 'view_password_hash'))->toBeFalse()
        ->and(Schema::hasColumn('galleries', 'download_pin_hash'))->toBeFalse();
});
