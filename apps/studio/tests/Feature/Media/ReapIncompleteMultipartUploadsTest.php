<?php

use App\Media\Console\Commands\ReapIncompleteMultipartUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

// The command itself talks to a real Aws\S3\S3Client (Storage::disk('s3')
// ->getClient()) — no fake for that boundary exists in this repo yet
// (Storage::fake('s3') swaps the driver for local disk, which has no
// multipart-upload API to exercise), so this proves the seam is wired
// rather than re-testing the AWS SDK's own ListMultipartUploads/
// AbortMultipartUpload calls.
it('registers the reaper command with artisan', function () {
    expect(Artisan::all())->toHaveKey('media:reap-multipart-uploads');
});

it('quarantines the same prefix a future multipart upload (G-02) would use', function () {
    expect(ReapIncompleteMultipartUploads::QUARANTINE_PREFIX)->toBe('quarantine/');
});

it('schedules the reaper to run daily', function () {
    // bootstrap/app.php's withSchedule() callback only runs once the console
    // kernel actually boots (Artisan::starting) — a plain app(Schedule::class)
    // in a feature test resolves an empty Schedule, since nothing here has
    // run an artisan command yet. `inspire` is the cheapest one to boot with.
    Artisan::call('inspire');

    $events = app(Schedule::class)->events();

    $reaper = collect($events)->first(
        fn ($event) => str_contains($event->command ?? '', 'media:reap-multipart-uploads')
    );

    expect($reaper)->not->toBeNull()
        ->and($reaper->expression)->toBe('0 0 * * *');
});
