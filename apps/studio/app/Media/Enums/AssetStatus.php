<?php

namespace App\Media\Enums;

/**
 * Terminal, and only ever written once by App\Gallery\Actions\
 * IngestCompletedUpload — there is no in-between "processing" state
 * persisted, because that action runs the whole pipeline to a conclusion
 * before it ever writes the row. Failed is not an error state the operator
 * needs to be paged for: it is the expected outcome for a corrupt file or a
 * format this pipeline does not decode, and it feeds G-04's "needs
 * attention" tray rather than Horizon's failed-jobs list.
 */
enum AssetStatus: string
{
    case Ready = 'ready';
    case Failed = 'failed';
}
