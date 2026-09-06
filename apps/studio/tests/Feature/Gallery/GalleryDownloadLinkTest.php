<?php

use App\Gallery\Actions\IssueGalleryDownloadLink;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    $this->gallery = Gallery::factory()->create();
});

it('mints a link that redirects into the download screen', function () {
    $url = app(IssueGalleryDownloadLink::class)($this->gallery);

    expect(GalleryDownloadLink::count())->toBe(1);

    $this->get($url)->assertRedirect("/galerien/{$this->gallery->public_id}/download");
});

it('stops working once the token expires', function () {
    $url = app(IssueGalleryDownloadLink::class)($this->gallery);

    $this->travel(GalleryDownloadLink::VALID_FOR_DAYS + 1)->days();

    $this->get($url)->assertNotFound();
});

it('refuses a token minted for a different gallery', function () {
    $url = app(IssueGalleryDownloadLink::class)($this->gallery);
    $other = Gallery::factory()->create();

    $token = str($url)->afterLast('/')->toString();

    $this->get("/galerien/{$other->public_id}/download/mail/{$token}")->assertNotFound();
});
