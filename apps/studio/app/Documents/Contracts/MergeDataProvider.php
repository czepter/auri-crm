<?php

namespace App\Documents\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * One module's share of the merge-field array: the blocks it owns, for a
 * case or, without one, as empty strings. Tagged in AppServiceProvider and
 * gathered by DocumentMergeData, which sits below the modules owning the
 * data and so can only ask for it.
 */
interface MergeDataProvider
{
    /** @return array<string, array<string, mixed>> */
    public function blocks(?Model $case): array;
}
