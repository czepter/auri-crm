<?php

namespace App\Media\Support;

use RuntimeException;

/** A `Range` header ByteRange::parse() cannot satisfy against the object's actual size — a 416 (RFC 9110 §14.1.2's only defined error response). */
class RangeNotSatisfiableException extends RuntimeException
{
    public function __construct(public readonly int $totalSize)
    {
        parent::__construct("Range not satisfiable against a {$totalSize}-byte object.");
    }
}
