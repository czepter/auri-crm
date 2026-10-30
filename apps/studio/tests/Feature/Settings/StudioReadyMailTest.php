<?php

// apps/studio/tests/Feature/Settings/StudioReadyMailTest.php

use App\Enums\StaffRole;
use App\Models\StudioSettings;
use App\Staff\Notifications\StudioReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // See tests/Feature/Settings/OnboardingTest.php's own beforeEach: the
    // global hook stamps every studio as already onboarded, this test needs
    // the unfinished state to exercise complete() honestly.
    //
    // mail.inbound.domain is unset by default in the test environment (see
    // InboundMailTest/InboundMimeMessageTest, which set it themselves for
    // the same reason) — without it InboundAddress::catchAll() always
    // returns null regardless of inbound_secret, and the assertion below
    // would pass on any mail's boilerplate CSS "@media" rule rather than on
    // the address this test is actually about.
    config()->set('mail.inbound.domain', inboundDomain());
    StudioSettings::query()->update(['onboarding_completed_at' => null, 'inbound_secret' => 'a-known-secret']);
    Cache::store('redis')->flush();
});

it('sends the ready mail carrying the inbound address on completion', function () {
    Notification::fake();
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')->post('/einrichtung/studio/abschluss')->assertRedirect('/');

    Notification::assertSentTo($owner, function (StudioReady $notification) use ($owner) {
        $html = (string) $notification->toMail($owner)->render();

        return str_contains($html, '@'.inboundDomain());
    });
});

it('drops the inbound block when the studio never rotated an inbound secret', function () {
    StudioSettings::query()->update(['inbound_secret' => null]);
    Notification::fake();
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')->post('/einrichtung/studio/abschluss')->assertRedirect('/');

    Notification::assertSentTo($owner, function (StudioReady $notification) use ($owner) {
        $html = (string) $notification->toMail($owner)->render();

        return ! str_contains($html, 'Anfragen per Mail')
            && ! str_contains($html, 'Posteingang öffnen')
            && str_contains($html, 'eine eigene Adresse');
    });
});
