<?php

use App\Quotes\Actions\AllocateQuoteNumber;
use Illuminate\Contracts\Console\Kernel;

/**
 * Test-only harness for AllocateQuoteNumberTest's concurrency proof.
 *
 * A single PHP process is single-threaded, so proving "two concurrent
 * allocations never collide" needs allocations that are actually happening
 * at once — real OS processes, each with its own DB connection, not a tight
 * loop inside one process. This script is what each of those processes
 * runs: boot the app exactly as `artisan` does, allocate a number, and
 * report the result as JSON on stdout (or the failure reason on stderr).
 *
 * Never registered as an artisan command and never invoked outside the test
 * suite — it exists only so the test can spawn it with Process::pool().
 */
require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$tenantId, $year] = [$argv[1], (int) $argv[2]];

try {
    $result = app(AllocateQuoteNumber::class)($tenantId, $year);
    fwrite(STDOUT, json_encode($result));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
