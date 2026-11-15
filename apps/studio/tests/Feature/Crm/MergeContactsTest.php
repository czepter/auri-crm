<?php

use App\Crm\Actions\MergeContacts;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Portal\Models\ClientAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function references(string $sql): array
{
    return collect(DB::select($sql))->pluck('ref')->sort()->values()->all();
}

it('handles every foreign key that points at contacts', function () {
    $foreignKeys = references(<<<'SQL'
        select k.conrelid::regclass::text || '.' || a.attname as ref
        from pg_constraint k
        join pg_attribute a on a.attrelid = k.conrelid and a.attnum = any (k.conkey)
        where k.contype = 'f' and k.confrelid = 'contacts'::regclass
    SQL);

    $handled = collect([...MergeContacts::MOVED, ...array_keys(MergeContacts::MOVED_UNIQUE), ...MergeContacts::REVOKED])->sort()->values()->all();

    expect($foreignKeys)->toBe($handled);
});

it('handles every polymorphic and json reference to a contact', function () {
    $morphs = references(<<<'SQL'
        select t.table_name || '.' || regexp_replace(t.column_name, '_type$', '') as ref
        from information_schema.columns t
        join information_schema.columns i
          on i.table_schema = t.table_schema and i.table_name = t.table_name
         and i.column_name = regexp_replace(t.column_name, '_type$', '') || '_id'
        where t.table_schema = 'public' and t.column_name like '%\_type'
    SQL);

    // Morphs that never hold a Contact: shoot/case anchors, staff principals, spatie roles.
    $foreign = ['automation_runs.anchorable', 'idempotency_keys.principal', 'model_has_permissions.model', 'model_has_roles.model'];

    $lists = references(<<<'SQL'
        select table_name || '.' || column_name as ref
        from information_schema.columns
        where table_schema = 'public' and data_type in ('json', 'jsonb') and column_name like '%contact%'
    SQL);

    expect(array_values(array_diff($morphs, $foreign)))->toBe(collect(MergeContacts::MORPHS)->sort()->values()->all())
        ->and($lists)->toBe(MergeContacts::JSON_LISTS);
});

it('merges the chosen fields, drops colliding rows and revokes the duplicate portal access', function () {
    $staff = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);

    $survivor = Contact::factory()->create([
        'name' => 'Anna Beispiel', 'email' => 'anna@example.test', 'phone' => '0171 111', 'city' => 'Jena',
        'social_profiles' => [['platform' => 'instagram', 'value' => '@anna']],
    ]);
    $duplicate = Contact::factory()->create([
        'name' => 'Anna B.', 'email' => 'anna.b@example.test', 'phone' => '0171 222', 'city' => 'Berlin',
        'social_profiles' => [['platform' => 'instagram', 'value' => '@ANNA'], ['platform' => 'tiktok', 'value' => '@anna.b']],
    ]);

    $shared = ClientCase::factory()->create();
    $shared->attachContact($survivor, CaseContactRole::Partner);
    $shared->attachContact($duplicate, CaseContactRole::Partner);
    $only = ClientCase::factory()->create();
    $only->attachContact($duplicate, CaseContactRole::Primary);

    $task = Task::factory()->create(['contact_id' => $duplicate->id]);

    $link = ClientAccess::create(['contact_id' => $duplicate->id, 'token_hash' => ClientAccess::hash('dup'), 'expires_at' => now()->addHour()]);
    $keep = ClientAccess::create(['contact_id' => $survivor->id, 'token_hash' => ClientAccess::hash('keep'), 'expires_at' => now()->addHour()]);
    $duplicate->createToken('guest');

    $this->actingAs($staff, 'staff')
        ->post("/kontakte/{$survivor->public_id}/zusammenfuehren", [
            'duplicate' => $duplicate->public_id,
            'choices' => ['name' => 'duplicate', 'email' => 'duplicate', 'phone' => 'survivor'],
        ])
        ->assertRedirect("/kontakte/{$survivor->public_id}");

    $merged = $survivor->fresh();

    expect(Contact::find($duplicate->id))->toBeNull()
        ->and($merged->only(['name', 'email', 'phone', 'city']))->toBe(['name' => 'Anna B.', 'email' => 'anna.b@example.test', 'phone' => '0171 111', 'city' => 'Jena'])
        ->and($merged->social_profiles)->toEqual([['platform' => 'instagram', 'value' => '@anna'], ['platform' => 'tiktok', 'value' => '@anna.b']])
        ->and($shared->contacts()->pluck('contacts.id')->all())->toBe([$survivor->id])
        ->and($only->contacts()->pluck('contacts.id')->all())->toBe([$survivor->id])
        ->and($task->fresh()->contact_id)->toBe($survivor->id)
        ->and(ClientAccess::find($link->id))->toBeNull()
        ->and(ClientAccess::find($keep->id))->not->toBeNull()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $duplicate->id)->where('tokenable_type', Contact::class)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $survivor->id)->exists())->toBeFalse();

    $audit = AuditLog::where('event', 'contact.merged')->sole();

    expect($audit->subject)->toBe($survivor->public_id)
        ->and($audit->context['duplicate'])->toBe($duplicate->public_id)
        ->and($audit->context['choices'])->toMatchArray(['name' => 'duplicate', 'email' => 'duplicate', 'phone' => 'survivor']);
});

it('refuses a contributor, the same contact twice and an erased contact', function () {
    $survivor = Contact::factory()->create();
    $duplicate = Contact::factory()->create();
    $erased = Contact::factory()->create(['dsgvo_erased_at' => now()]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Contributor]), 'staff')
        ->post("/kontakte/{$survivor->public_id}/zusammenfuehren", ['duplicate' => $duplicate->public_id])
        ->assertForbidden();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->post("/kontakte/{$survivor->public_id}/zusammenfuehren", ['duplicate' => $survivor->public_id])
        ->assertSessionHasErrors('duplicate');

    $this->post("/kontakte/{$survivor->public_id}/zusammenfuehren", ['duplicate' => $erased->public_id])
        ->assertUnprocessable();

    expect(Contact::count())->toBe(3);
});

it('keeps one case row with the more significant role, audits the email and caps the profiles', function () {
    $survivor = Contact::factory()->create([
        'email' => 'a@example.test',
        'social_profiles' => collect(range(1, 8))->map(fn (int $n) => ['platform' => 'instagram', 'value' => "@a{$n}"])->all(),
    ]);
    $duplicate = Contact::factory()->create([
        'email' => 'b@example.test',
        'social_profiles' => collect(range(1, 8))->map(fn (int $n) => ['platform' => 'tiktok', 'value' => "@b{$n}"])->all(),
    ]);

    $case = ClientCase::factory()->create();
    $case->attachContact($survivor, CaseContactRole::Partner);
    $case->attachContact($duplicate, CaseContactRole::Primary);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff')
        ->post("/kontakte/{$survivor->public_id}/zusammenfuehren", ['duplicate' => $duplicate->public_id, 'choices' => ['email' => 'duplicate']])
        ->assertRedirect();

    $rows = DB::table('case_contacts')->where('case_id', $case->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->sole()->contact_id)->toBe($survivor->id)
        ->and($rows->sole()->role)->toBe('primary')
        ->and($survivor->fresh()->social_profiles)->toHaveCount(10)
        ->and($survivor->fresh()->social_profiles[0])->toEqual(['platform' => 'instagram', 'value' => '@a1'])
        ->and(AuditLog::where('event', 'contact.email_changed')->sole()->context)->toEqual(['from' => 'a@example.test', 'to' => 'b@example.test']);
});

it('refuses a manager without the DSGVO capability', function () {
    $survivor = Contact::factory()->create();
    $duplicate = Contact::factory()->create();
    $manager = User::factory()->withMfa()->create(['role' => StaffRole::Manager]);

    $this->actingAs($manager, 'staff')
        ->post("/kontakte/{$survivor->public_id}/zusammenfuehren", ['duplicate' => $duplicate->public_id])
        ->assertForbidden();
    $this->get("/kontakte/{$survivor->public_id}/zusammenfuehren/{$duplicate->public_id}")->assertForbidden();

    expect(Contact::count())->toBe(2);
});
