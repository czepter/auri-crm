<?php

use App\Media\Services\MultipartUploadGateway;
use Aws\MockHandler;
use Aws\Result;

// mockS3Client() lives in tests/Pest.php — see the note there.

/**
 * The technical risk this whole story turns on: SigV4 normally hashes a
 * request body up front and then rewinds the stream to actually send it,
 * which a genuinely non-seekable stream — exactly what php://input always
 * is on a real request — can never do. uploadPart()'s `v4-unsigned-body`
 * signature option is what has to make that not throw. popen() is used
 * rather than php://temp or php://memory because both of those ARE
 * seekable and would let this test pass by accident.
 */
it('uploads a part from a genuinely non-seekable stream without buffering or seeking it', function () {
    $bytes = 'streamed part bytes';
    $resource = popen('printf %s '.escapeshellarg($bytes), 'r');
    expect(stream_get_meta_data($resource)['seekable'])->toBeFalse();

    $handler = new MockHandler([new Result(['ETag' => '"part-etag"'])]);
    $gateway = new MultipartUploadGateway(mockS3Client($handler));

    $etag = $gateway->uploadPart('quarantine/x/file.jpg', 'upload-1', 1, $resource, strlen($bytes));

    expect($etag)->toBe('"part-etag"')
        ->and((string) $handler->getLastRequest()->getBody())->toBe($bytes)
        ->and($handler->getLastRequest()->getHeaderLine('X-Amz-Content-Sha256'))->toBe('UNSIGNED-PAYLOAD');

    fclose($resource);
});

it('lists parts across pages, in the shape the gateway promises', function () {
    $handler = new MockHandler([
        new Result([
            'Parts' => [['PartNumber' => 1, 'Size' => 100, 'ETag' => '"a"']],
            'IsTruncated' => true,
            'NextPartNumberMarker' => '1',
        ]),
        new Result([
            'Parts' => [['PartNumber' => 2, 'Size' => 50, 'ETag' => '"b"']],
            'IsTruncated' => false,
        ]),
    ]);
    $gateway = new MultipartUploadGateway(mockS3Client($handler));

    $parts = $gateway->listParts('quarantine/x/file.jpg', 'upload-1');

    expect($parts)->toBe([
        ['part_number' => 1, 'size' => 100, 'etag' => '"a"'],
        ['part_number' => 2, 'size' => 50, 'etag' => '"b"'],
    ]);
});
