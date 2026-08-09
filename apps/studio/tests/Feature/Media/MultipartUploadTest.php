<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Media\Console\Commands\ReapIncompleteMultipartUploads;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\MultipartUpload;
use Aws\Command;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// staffMember() and mockS3Client() live in tests/Pest.php — see the notes
// there.

/** @return array{filename: string, file_size: int, file_modified_at: int} */
function m01FileFingerprint(string $filename = 'IMG_4788.jpg', int $size = 24_800_000, int $modifiedAtMs = 1_749_000_000_000): array
{
    return ['filename' => $filename, 'file_size' => $size, 'file_modified_at' => $modifiedAtMs];
}

it('starts a fresh multipart upload under the quarantine prefix', function () {
    $gallery = Gallery::factory()->create();
    $handler = new MockHandler([new Result(['UploadId' => 'upload-abc'])]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $response = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->postJson("/galerien/{$gallery->public_id}/uploads", m01FileFingerprint())
        ->assertOk();

    $response->assertJsonStructure(['upload' => ['public_id', 'part_size'], 'parts']);
    expect($response->json('parts'))->toBe([]);
    expect($response->json('upload.part_size'))->toBe(8 * 1024 * 1024);

    $upload = MultipartUpload::sole();
    expect($upload->public_id)->toBe($response->json('upload.public_id'))
        ->and($upload->gallery_id)->toBe($gallery->id)
        ->and($upload->upload_id)->toBe('upload-abc')
        ->and($upload->status)->toBe(MultipartUploadStatus::InProgress)
        ->and($upload->key)->toStartWith(ReapIncompleteMultipartUploads::QUARANTINE_PREFIX)
        ->and($upload->key)->toEndWith('/IMG_4788.jpg');

    $sent = $handler->getLastCommand()->toArray();
    expect($sent['Key'])->toBe($upload->key)
        ->and($sent['Bucket'])->toBe(config('filesystems.disks.s3.bucket'));
});

it('never puts a storage path, bucket name or upload id in the response body', function () {
    $gallery = Gallery::factory()->create();
    $handler = new MockHandler([new Result(['UploadId' => 'super-secret-upload-id'])]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $response = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->postJson("/galerien/{$gallery->public_id}/uploads", m01FileFingerprint());

    expect($response->getContent())
        ->not->toContain('super-secret-upload-id')
        ->not->toContain(config('filesystems.disks.s3.bucket'))
        ->not->toContain(ReapIncompleteMultipartUploads::QUARANTINE_PREFIX);
});

it('resumes the same in-progress session for a returning tab, asking the bucket rather than its own bookkeeping', function () {
    // One handler, bound once: MultipartUploadController is resolved once
    // per Route object, and Illuminate\Routing\Route::getController() caches
    // that resolution for as long as the route lives — which, inside one
    // test process hitting the same route twice, is both calls. Rebinding
    // S3Client::class between them would only ever reach a controller built
    // afresh, which real (non-Octane) request lifecycles get for free but a
    // single test method does not.
    $gallery = Gallery::factory()->create();
    $fingerprint = m01FileFingerprint();
    $user = staffMember(StaffRole::Owner);

    $handler = new MockHandler([
        new Result(['UploadId' => 'upload-abc']),
        // A reload: the tab supplies the identical fingerprint again. The
        // bucket (ListParts), not a second CreateMultipartUpload, answers
        // what already landed.
        new Result([
            'Parts' => [['PartNumber' => 1, 'Size' => 8 * 1024 * 1024, 'ETag' => '"part-1-etag"']],
            'IsTruncated' => false,
        ]),
    ]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $first = $this->actingAs($user, 'staff')
        ->postJson("/galerien/{$gallery->public_id}/uploads", $fingerprint)
        ->assertOk();
    $second = $this->actingAs($user, 'staff')
        ->postJson("/galerien/{$gallery->public_id}/uploads", $fingerprint)
        ->assertOk();

    expect($second->json('upload.public_id'))->toBe($first->json('upload.public_id'))
        ->and($second->json('parts'))->toBe([['part_number' => 1, 'size' => 8 * 1024 * 1024, 'etag' => '"part-1-etag"']])
        ->and(MultipartUpload::count())->toBe(1);
});

it('retires a stale session and starts fresh when the bucket no longer knows its uploadId', function () {
    $gallery = Gallery::factory()->create();
    $fingerprint = m01FileFingerprint();
    $user = staffMember(StaffRole::Owner);

    $handler = new MockHandler([
        new Result(['UploadId' => 'upload-old']),
        // The reaper (or an operator) aborted upload-old server-side since.
        new S3Exception('The specified upload does not exist.', new Command('ListParts'), ['code' => 'NoSuchUpload']),
        new Result(['UploadId' => 'upload-new']),
    ]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $this->actingAs($user, 'staff')->postJson("/galerien/{$gallery->public_id}/uploads", $fingerprint)->assertOk();

    $response = $this->actingAs($user, 'staff')
        ->postJson("/galerien/{$gallery->public_id}/uploads", $fingerprint)
        ->assertOk();

    expect(MultipartUpload::count())->toBe(2);
    $old = MultipartUpload::where('upload_id', 'upload-old')->sole();
    $new = MultipartUpload::where('upload_id', 'upload-new')->sole();

    expect($old->status)->toBe(MultipartUploadStatus::Aborted)
        ->and($new->status)->toBe(MultipartUploadStatus::InProgress)
        ->and($response->json('upload.public_id'))->toBe($new->public_id);
});

it('streams a part straight through to UploadPart and hands back its etag', function () {
    $upload = MultipartUpload::factory()->create();
    $handler = new MockHandler([new Result(['ETag' => '"part-1-etag"'])]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $bytes = str_repeat('x', 1024);
    $response = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->call(
            'PUT',
            "/uploads/{$upload->public_id}/teile/1",
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) strlen($bytes),
            ]),
            $bytes
        );

    $response->assertOk();
    expect($response->json())->toBe(['part_number' => 1, 'etag' => '"part-1-etag"']);

    $sentCommand = $handler->getLastCommand()->toArray();
    expect($sentCommand['PartNumber'])->toBe(1)
        ->and($sentCommand['UploadId'])->toBe($upload->upload_id)
        // The actual bytes Guzzle put on the wire, read from the final PSR-7
        // request rather than from the command's own params — those still
        // hold the raw, unread php://temp resource $sentCommand['Body']
        // casts to "Resource id #N", not its contents.
        ->and((string) $handler->getLastRequest()->getBody())->toBe($bytes);
});

it('rejects a part number outside the S3 1..10000 range', function () {
    $upload = MultipartUpload::factory()->create();
    app()->instance(S3Client::class, mockS3Client(new MockHandler));

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->call('PUT', "/uploads/{$upload->public_id}/teile/10001", [], [], [], $this->transformHeadersToServerVars([
            'Content-Length' => '3',
        ]), 'abc')
        ->assertStatus(422);
});

it('refuses to upload a part into a session that is no longer in progress', function () {
    $upload = MultipartUpload::factory()->create(['status' => MultipartUploadStatus::Completed]);
    app()->instance(S3Client::class, mockS3Client(new MockHandler));

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->call('PUT', "/uploads/{$upload->public_id}/teile/1", [], [], [], $this->transformHeadersToServerVars([
            'Content-Length' => '3',
        ]), 'abc')
        ->assertStatus(409);
});

it('completes an upload by assembling the parts list from ListParts, never from the browser', function () {
    $upload = MultipartUpload::factory()->create();
    $handler = new MockHandler([
        new Result(['Parts' => [
            ['PartNumber' => 1, 'Size' => 8 * 1024 * 1024, 'ETag' => '"etag-1"'],
            ['PartNumber' => 2, 'Size' => 1024, 'ETag' => '"etag-2"'],
        ], 'IsTruncated' => false]),
        new Result(['ETag' => '"final-etag"', 'Location' => 'ignored']),
    ]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->postJson("/uploads/{$upload->public_id}/abschluss")
        ->assertOk()
        ->assertJson(['status' => 'completed']);

    expect($upload->fresh()->status)->toBe(MultipartUploadStatus::Completed)
        ->and($upload->fresh()->completed_at)->not->toBeNull();

    $completeCommand = $handler->getLastCommand()->toArray();
    expect($completeCommand['MultipartUpload']['Parts'])->toBe([
        ['PartNumber' => 1, 'ETag' => '"etag-1"'],
        ['PartNumber' => 2, 'ETag' => '"etag-2"'],
    ]);
});

it('refuses to complete an upload twice', function () {
    $upload = MultipartUpload::factory()->create(['status' => MultipartUploadStatus::Completed]);
    app()->instance(S3Client::class, mockS3Client(new MockHandler));

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->postJson("/uploads/{$upload->public_id}/abschluss")
        ->assertStatus(409);
});

it('aborts an in-progress upload on the operator\'s own cancel', function () {
    $upload = MultipartUpload::factory()->create();
    $handler = new MockHandler([new Result([])]);
    app()->instance(S3Client::class, mockS3Client($handler));

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->deleteJson("/uploads/{$upload->public_id}")
        ->assertOk()
        ->assertJson(['status' => 'aborted']);

    expect($upload->fresh()->status)->toBe(MultipartUploadStatus::Aborted)
        ->and($upload->fresh()->aborted_at)->not->toBeNull();

    $sent = $handler->getLastCommand()->toArray();
    expect($sent['UploadId'])->toBe($upload->upload_id)
        ->and($sent['Key'])->toBe($upload->key);
});

it('refuses a role without ManageShoots, and lets one with it through', function (StaffRole $role, int $status) {
    $gallery = Gallery::factory()->create();
    app()->instance(S3Client::class, mockS3Client(new MockHandler([new Result(['UploadId' => 'upload-abc'])])));

    $this->actingAs(staffMember($role), 'staff')
        ->postJson("/galerien/{$gallery->public_id}/uploads", m01FileFingerprint())
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 200],
    [StaffRole::SecondShooter, 403],
    [StaffRole::Assistant, 403],
]);

it('reaches the ingest screen authenticated, with no integer id among its props', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeitstag']);

    $response = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/galerien/{$gallery->public_id}/medien")
        ->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['target']['galleryId'])->toBe($gallery->public_id)
        ->and($props['target']['galleryTitle'])->toBe('Hochzeitstag')
        ->and($props['target']['partSize'])->toBe(8 * 1024 * 1024)
        ->and(integerIdPaths($props))->toBe([]);
});
