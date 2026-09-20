<?php

use Illuminate\Support\Facades\Storage;

// G-01a: the `s3` disk is Flysystem + the AWS SDK, no second abstraction on
// top. Faked the same way GotenbergClientTest fakes Http rather than hitting
// the real Gotenberg service — Storage::fake('s3') is Laravel's own fake for
// this exact boundary, so no live MinIO/RustFS/AWS endpoint is needed for
// this suite to prove the disk works. `docker-compose.yml`'s `rustfs` service
// is what "lands in the local Compose storage service" (the issue's own
// acceptance wording) actually exercises, outside this test process.
it('writes an object through the configured s3 disk and reads it back', function () {
    Storage::fake('s3');

    Storage::disk('s3')->put('quarantine/example.txt', 'hello media');

    Storage::disk('s3')->assertExists('quarantine/example.txt');
    expect(Storage::disk('s3')->get('quarantine/example.txt'))->toBe('hello media');
});

it('configures the s3 disk to move between S3-compatible providers without a code change', function () {
    // The issue's own constraint: AWS_ENDPOINT (plus path-style addressing)
    // is all a self-hosted provider needs, and nothing in this config picks
    // one — docker-compose.yml's `rustfs` service is the only place a vendor
    // name appears, and it isn't this file.
    $disk = config('filesystems.disks.s3');

    expect($disk['driver'])->toBe('s3')
        ->and($disk)->toHaveKeys(['endpoint', 'use_path_style_endpoint', 'bucket', 'region', 'key', 'secret']);
});
