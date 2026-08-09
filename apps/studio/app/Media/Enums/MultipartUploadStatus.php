<?php

namespace App\Media\Enums;

/**
 * in_progress -> completed|aborted, and never back. A row past either end
 * state stays exactly where the fingerprint index in the migration expects
 * it: out of the way of a genuinely new upload of the same file.
 */
enum MultipartUploadStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Aborted = 'aborted';
}
