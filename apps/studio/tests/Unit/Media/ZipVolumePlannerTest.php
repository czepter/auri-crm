<?php

use App\Media\Support\ZipBuildMember;
use App\Media\Support\ZipVolumePlanner;

// Pure grouping logic, no storage or zip writing involved — #59's own
// steer: unit-test the size-accumulation/splitting logic in isolation from
// real S3 I/O, with a threshold far smaller than the real 3.9GiB constant
// so this never needs an actual multi-gigabyte fixture.

function member(string $name, int $size): ZipBuildMember
{
    return new ZipBuildMember(key: "originals/{$name}", name: $name, size: $size);
}

it('keeps every member in one volume while the cumulative size stays under the threshold', function () {
    $members = [member('a.jpg', 40), member('b.jpg', 40), member('c.jpg', 10)];

    $volumes = ZipVolumePlanner::plan($members, thresholdBytes: 100);

    expect($volumes)->toHaveCount(1)
        ->and($volumes[0])->toBe($members);
});

it('closes the current volume once the next member would cross the threshold, rather than exceeding it', function () {
    $a = member('a.jpg', 60);
    $b = member('b.jpg', 60); // 60 + 60 = 120 > 100 — must start a new volume
    $c = member('c.jpg', 30); // 60 + 30 = 90 <= 100 — stays with b

    $volumes = ZipVolumePlanner::plan([$a, $b, $c], thresholdBytes: 100);

    expect($volumes)->toHaveCount(2)
        ->and($volumes[0])->toBe([$a])
        ->and($volumes[1])->toBe([$b, $c]);

    foreach ($volumes as $volume) {
        expect(array_sum(array_map(fn ($m) => $m->size, $volume)))->toBeLessThanOrEqual(100);
    }
});

it('gives a single member already at or beyond the threshold a volume of its own, never splitting it', function () {
    $huge = member('huge.jpg', 500);
    $small = member('small.jpg', 10);

    $volumes = ZipVolumePlanner::plan([$huge, $small], thresholdBytes: 100);

    expect($volumes)->toHaveCount(2)
        ->and($volumes[0])->toBe([$huge])
        ->and($volumes[1])->toBe([$small]);
});

it('exercises the split boundary repeatedly across many members, exactly like a large gallery would', function () {
    // 25 members at 45 bytes each = 1125 bytes total; a 100-byte threshold
    // fits 2 per volume (90 <= 100, a 3rd would make 135 > 100).
    $members = array_map(fn ($i) => member("img{$i}.jpg", 45), range(1, 25));

    $volumes = ZipVolumePlanner::plan($members, thresholdBytes: 100);

    expect($volumes)->toHaveCount(13); // 12 full pairs + 1 leftover single
    foreach (array_slice($volumes, 0, -1) as $volume) {
        expect($volume)->toHaveCount(2);
    }
    expect(array_merge(...$volumes))->toBe($members); // nothing lost, nothing reordered
});

it('returns no volumes for no members', function () {
    expect(ZipVolumePlanner::plan([], thresholdBytes: 100))->toBe([]);
});

it('never produces a volume over the threshold end to end, for a mixed realistic distribution', function () {
    $members = [
        member('a.jpg', 30), member('b.jpg', 90), member('c.jpg', 5),
        member('d.jpg', 5), member('e.jpg', 95), member('f.jpg', 1),
    ];

    $volumes = ZipVolumePlanner::plan($members, thresholdBytes: 100);

    foreach ($volumes as $volume) {
        $size = array_sum(array_map(fn ($m) => $m->size, $volume));
        expect($size)->toBeLessThanOrEqual(100);
    }
    expect(array_merge(...$volumes))->toBe($members);
});
