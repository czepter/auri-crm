<?php

use App\Engagement\Data\ThreadData;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('names exactly the contacts a staff reply would reach', function () {
    $project = bookedContactProject();
    $contact = $project->contacts()->sole();
    $contact->update(['email' => 'lena@example.test', 'name' => 'Lena Meier']);

    $thread = Thread::factory()->for($project, 'case')->create();
    $thread->participants()->create(['contact_id' => $contact->id]);
    // The staff member PostMessage records on a reply is a participant too,
    // and never a recipient.
    $thread->participants()->create(['user_id' => staffMember(StaffRole::Admin)->id]);

    expect(ThreadData::fromModel($thread->fresh())->recipients)->toBe(['Lena Meier']);
});

it('leaves out a contact without an address, which no mail could reach', function () {
    $project = bookedContactProject();
    $contact = $project->contacts()->sole();
    $contact->update(['email' => null]);

    $thread = Thread::factory()->for($project, 'case')->create();
    $thread->participants()->create(['contact_id' => $contact->id]);

    expect(ThreadData::fromModel($thread->fresh())->recipients)->toBe([]);
});
