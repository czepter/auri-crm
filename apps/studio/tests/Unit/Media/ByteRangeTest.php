<?php

use App\Media\Support\ByteRange;
use App\Media\Support\RangeNotSatisfiableException;

// Pure header-parsing logic, no S3 or HTTP involved — G-11's own correctness
// risk lives entirely here: get this wrong and a resume either restarts
// (Storage::download()'s own failure mode) or reassembles corrupt.

it('returns null for no Range header — the caller serves the whole object', function () {
    expect(ByteRange::parse(null, 1000))->toBeNull()
        ->and(ByteRange::parse('', 1000))->toBeNull();
});

it('parses a simple bounded range', function () {
    $range = ByteRange::parse('bytes=0-99', 1000);

    expect($range->start)->toBe(0)
        ->and($range->end)->toBe(99)
        ->and($range->length())->toBe(100)
        ->and($range->forS3())->toBe('bytes=0-99')
        ->and($range->contentRange(1000))->toBe('bytes 0-99/1000');
});

it('parses an open-ended range as "to the end of the object"', function () {
    $range = ByteRange::parse('bytes=900-', 1000);

    expect($range->start)->toBe(900)
        ->and($range->end)->toBe(999)
        ->and($range->length())->toBe(100);
});

it('parses a suffix range as "the last N bytes"', function () {
    $range = ByteRange::parse('bytes=-100', 1000);

    expect($range->start)->toBe(900)
        ->and($range->end)->toBe(999)
        ->and($range->length())->toBe(100);
});

it('clamps an end past the object size down to the last byte, rather than rejecting it', function () {
    $range = ByteRange::parse('bytes=900-999999', 1000);

    expect($range->start)->toBe(900)
        ->and($range->end)->toBe(999);
});

it('rejects a start at or past the object size as unsatisfiable', function () {
    expect(fn () => ByteRange::parse('bytes=1000-', 1000))
        ->toThrow(RangeNotSatisfiableException::class);
});

it('rejects a malformed Range header as unsatisfiable', function () {
    expect(fn () => ByteRange::parse('not-a-range', 1000))
        ->toThrow(RangeNotSatisfiableException::class);

    expect(fn () => ByteRange::parse('bytes=-', 1000))
        ->toThrow(RangeNotSatisfiableException::class);
});

it('carries the total size an unsatisfiable range failed against, for the 416 response', function () {
    try {
        ByteRange::parse('bytes=5000-', 1000);
        expect(false)->toBeTrue('expected an exception');
    } catch (RangeNotSatisfiableException $e) {
        expect($e->totalSize)->toBe(1000);
    }
});
