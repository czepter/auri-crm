<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Minor fix round 1: normalisation used to be a convention only
// SignUpForPrerelease's own insertOrIgnore() call site honoured — any other
// write, like this plain create(), used to bypass it entirely.
it('normalises the email through a plain create(), not only through SignUpForPrerelease', function () {
    $gallery = Gallery::factory()->create();

    $signup = GalleryPrereleaseSignup::create(['gallery_id' => $gallery->id, 'email' => 'Anna@Example.TEST']);

    expect($signup->email)->toBe('anna@example.test');
});
