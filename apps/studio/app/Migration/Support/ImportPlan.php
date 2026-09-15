<?php

namespace App\Migration\Support;

/**
 * The output of PlanHistoricalImport: everything a historical import run
 * would create, and every row it could not map, with a reason — the whole
 * of what a dry run reports (X-01's acceptance criterion) and exactly what
 * CommitHistoricalImport executes when --commit is passed. One plan, built
 * once and read twice, is what keeps the dry-run report from ever drifting
 * out of sync with what a commit actually writes.
 *
 * Each property is a shape of:
 *   ['toCreate' => list<array>, 'errors' => list<array{row:int,ref:?string,reason:string}>]
 * contacts additionally carries 'toReuse' — rows matched to an already
 * existing Contact by email rather than needing a new one.
 */
final class ImportPlan
{
    public function __construct(
        public readonly array $contacts,
        public readonly array $projects,
        public readonly array $jobs,
    ) {}
}
