<?php

use App\Support\Observability\TagSurface;
use App\Support\Surface;
use Sentry\Event;
use Sentry\State\Scope;

// F-11's acceptance criterion: "an exception raised on each of the three
// surfaces arrives tagged with that surface and no other". Asserted against
// the tag-setting callback in isolation — a Scope applied to a freshly
// constructed Event — rather than a live capture and network round-trip,
// exactly as the story calls for.
it('tags an event with exactly the surface that raised it, for each surface', function (string $surface) {
    config(['surface.name' => $surface]);

    $scope = new Scope;
    (new TagSurface)($scope);

    $event = $scope->applyToEvent(Event::createEvent());

    expect($event->getTags())->toBe(['surface' => $surface]);

    foreach (Surface::ALL as $other) {
        if ($other === $surface) {
            continue;
        }

        expect($event->getTags()['surface'])->not->toBe($other);
    }
})->with(Surface::ALL);
