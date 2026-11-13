<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Engagement\Models\ThreadParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

uses(RefreshDatabase::class);

it('belongs to a project and carries its messages oldest first', function () {
    $project = ClientCase::factory()->create();
    $thread = Thread::factory()->for($project, 'case')->create(['subject' => 'Ablauf & Gruppenfotos']);

    $first = Message::factory()->for($thread)->create(['created_at' => now()->subDay()]);
    $second = Message::factory()->for($thread)->create(['created_at' => now()]);

    expect($thread->case->is($project))->toBeTrue()
        ->and($thread->subject)->toBe('Ablauf & Gruppenfotos')
        ->and($thread->messages->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('gives a message exactly one author, a contact or a staff user', function () {
    $thread = Thread::factory()->create();

    Message::factory()->for($thread)->create(['contact_id' => Contact::factory(), 'author_id' => null]);
    Message::factory()->for($thread)->fromStaff()->create();

    expect(Message::count())->toBe(2);

    expect(fn () => Message::factory()->for($thread)->create(['contact_id' => null, 'author_id' => null]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => Message::factory()->for($thread)->create([
        'contact_id' => Contact::factory()->create()->id,
        'author_id' => User::factory()->create()->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a message with two authors even at the database, bypassing the model', function () {
    $thread = Thread::factory()->create();
    $contact = Contact::factory()->create();
    $user = User::factory()->create();

    expect(fn () => DB::table('messages')->insert([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => (string) new Cuid2,
        'thread_id' => $thread->id,
        'contact_id' => $contact->id,
        'author_id' => $user->id,
        'body' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('lives in its own tables, with no visibility flag anywhere on a message', function () {
    expect(Schema::hasTable('threads'))->toBeTrue()
        ->and(Schema::hasTable('messages'))->toBeTrue()
        ->and(Schema::hasTable('thread_participants'))->toBeTrue();

    // The acceptance criterion, made structural rather than trusted to a
    // filter someone remembers to apply: there is no column a write could
    // ever set to mark a message internal. C-07's InternalNote is a wholly
    // separate table, proven unreachable from the portal by its own test.
    $columns = Schema::getColumnListing('messages');

    expect(array_intersect($columns, ['internal', 'is_internal', 'visibility', 'visible']))->toBe([]);
});

it('records a participant the first time each side posts, and only once', function () {
    $thread = Thread::factory()->create();
    $contact = Contact::factory()->create();

    app(PostMessage::class)($thread, $contact, 'Erste Nachricht.');
    app(PostMessage::class)($thread, $contact, 'Zweite Nachricht.');

    expect(ThreadParticipant::where('thread_id', $thread->id)->where('contact_id', $contact->id)->count())->toBe(1);
});
