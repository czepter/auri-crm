<?php

use App\Media\Support\ExifFields;

// A pure function over exif_read_data()'s own array shape, tested directly
// against that shape rather than against a real EXIF-bearing JPEG — GD, this
// repo's only way to synthesize a JPEG fixture in a test, cannot write EXIF.

it('parses DateTimeOriginal, Make and Model out of exif_read_data\'s section layout', function () {
    $result = ExifFields::fromReadData([
        'EXIF' => ['DateTimeOriginal' => '2026:06:14 15:04:22'],
        'IFD0' => ['Make' => 'Canon', 'Model' => 'EOS R5'],
    ]);

    expect($result['takenAt']->toDateTimeString())->toBe('2026-06-14 15:04:22')
        ->and($result['cameraMake'])->toBe('Canon')
        ->and($result['cameraModel'])->toBe('EOS R5');
});

it('returns nulls when no EXIF sections are present at all', function () {
    $result = ExifFields::fromReadData([
        'FILE' => ['FileName' => 'test.jpg'],
        'COMPUTED' => ['Width' => 300, 'Height' => 200],
    ]);

    expect($result['takenAt'])->toBeNull()
        ->and($result['cameraMake'])->toBeNull()
        ->and($result['cameraModel'])->toBeNull();
});

it('tolerates a malformed DateTimeOriginal rather than throwing', function () {
    $result = ExifFields::fromReadData(['EXIF' => ['DateTimeOriginal' => 'not-a-date']]);

    expect($result['takenAt'])->toBeNull();
});

it('treats a blank Make/Model as absent rather than as an empty string', function () {
    $result = ExifFields::fromReadData(['IFD0' => ['Make' => '  ', 'Model' => '']]);

    expect($result['cameraMake'])->toBeNull()
        ->and($result['cameraModel'])->toBeNull();
});
