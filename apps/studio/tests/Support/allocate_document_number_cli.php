<?php

use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use Illuminate\Contracts\Console\Kernel;

/**
 * Test-only harness for AllocateDocumentNumberTest's concurrency proofs.
 *
 * A single PHP process is single-threaded, so proving "two concurrent
 * allocations never collide" needs allocations that are actually happening
 * at once — real OS processes, each with its own DB connection, not a tight
 * loop inside one process. This script is what each of those processes
 * runs: boot the app exactly as `artisan` does, allocate a number, and
 * report the result as JSON on stdout (or the failure reason on stderr).
 *
 * Generalises Q-05's allocate_quote_number_cli.php with an optional fourth
 * argv entry naming the document type, so the same harness proves the
 * concurrency guarantee for a second document type without a second script.
 *
 * Never registered as an artisan command and never invoked outside the test
 * suite — it exists only so the test can spawn it with Process::pool().
 */
require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$tenantId, $year] = [$argv[1], (int) $argv[2]];
$documentType = DocumentType::from($argv[3] ?? DocumentType::Quote->value);

// `artisan test --parallel` moves each worker onto its own database at
// runtime (studio_test_test_1, _2, …), which is a config value, not an
// environment variable — this child boots from .env and would otherwise
// allocate in a database the parent never looks at, leaving it counting zero
// rows against a race it just watched happen.
if (isset($argv[4])) {
    config(['database.connections.pgsql.database' => $argv[4]]);
}

try {
    $result = app(AllocateDocumentNumber::class)($tenantId, $year, $documentType);
    fwrite(STDOUT, json_encode($result));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
