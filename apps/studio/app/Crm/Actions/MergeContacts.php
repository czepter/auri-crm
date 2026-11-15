<?php

namespace App\Crm\Actions;

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\Contact;
use App\Crm\Support\SocialProfiles;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MergeContacts
{
    public const FIELDS = ['name', 'email', 'phone', 'street', 'postcode', 'city'];

    /** table.column of every foreign key to contacts, re-pointed to the survivor as is. */
    public const MOVED = [
        'leads.referred_by_contact_id',
        'tasks.contact_id',
        'quotes.accepted_by_contact_id',
        'quotes.recipient_contact_id',
        'messages.contact_id',
        'asset_favorites.contact_id',
        'form_answers.contact_id',
        'asset_comments.contact_id',
        'inbound_messages.contact_id',
        'gallery_lists.contact_id',
        'gallery_views.contact_id',
        'form_instance_files.contact_id',
        'invoices.contact_id',
        'contract_signatures.contact_id',
        'dsgvo_requests.contact_id',
    ];

    /**
     * Same, but a unique index spans the column: the duplicate's row is
     * dropped where the survivor already holds one with the same scope.
     *
     * @var array<string, list<string>>
     */
    public const MOVED_UNIQUE = [
        'case_contacts.contact_id' => ['case_id'],
        'thread_participants.contact_id' => ['thread_id'],
        'contract_signers.contact_id' => ['contract_id'],
        'service_suggestions.contact_id' => ['package_id'],
        'asset_hides.contact_id' => ['gallery_id', 'asset_key'],
    ];

    /** Portal access is revoked, never transferred. */
    public const REVOKED = [
        'client_accesses.contact_id',
        'contact_email_changes.contact_id',
    ];

    /** table.prefix of polymorphic references: tokens revoked, notifications moved. */
    public const MORPHS = [
        'personal_access_tokens.tokenable',
        'in_app_notifications.notifiable',
        'notification_preferences.notifiable',
    ];

    public const JSON_LISTS = ['automation_runs.contact_ids'];

    /** @param array<string, 'survivor'|'duplicate'> $choices */
    public function __invoke(Contact $survivor, Contact $duplicate, array $choices, ?User $actor = null): Contact
    {
        if ($survivor->is($duplicate)) {
            throw new InvalidArgumentException('A contact cannot be merged into itself.');
        }

        return DB::transaction(function () use ($survivor, $duplicate, $choices, $actor) {
            $attributes = [];

            foreach (self::FIELDS as $field) {
                if (($choices[$field] ?? 'survivor') === 'duplicate') {
                    $attributes[$field] = $duplicate->{$field};
                }
            }

            $attributes['social_profiles'] = SocialProfiles::union($survivor->social_profiles, $duplicate->social_profiles);

            if (array_key_exists('email', $attributes) && $attributes['email'] !== $survivor->email) {
                AuditLogger::record(
                    event: 'contact.email_changed',
                    actor: $actor,
                    context: ['from' => $survivor->email, 'to' => $attributes['email']],
                    subject: $survivor->public_id,
                );
            }

            $this->promoteCaseRoles($survivor, $duplicate);

            foreach (self::MOVED_UNIQUE as $reference => $scope) {
                [$table, $column] = explode('.', $reference);
                $this->dropDuplicatesHeldBySurvivor($table, $column, $scope, $survivor, $duplicate);
            }

            foreach ([...self::MOVED, ...array_keys(self::MOVED_UNIQUE)] as $reference) {
                [$table, $column] = explode('.', $reference);
                DB::table($table)->where($column, $duplicate->id)->update([$column => $survivor->id]);
            }

            foreach (self::REVOKED as $reference) {
                [$table, $column] = explode('.', $reference);
                DB::table($table)->where($column, $duplicate->id)->delete();
            }

            $duplicate->tokens()->delete();
            $this->moveNotifications($survivor, $duplicate);
            $this->rewriteAutomationRuns($survivor, $duplicate);

            $duplicate->delete();
            $survivor->update($attributes);

            AuditLogger::record(
                event: 'contact.merged',
                actor: $actor,
                context: [
                    'duplicate' => $duplicate->public_id,
                    'choices' => collect(self::FIELDS)->mapWithKeys(fn (string $field) => [$field => $choices[$field] ?? 'survivor'])->all(),
                ],
                subject: $survivor->public_id,
            );

            return $survivor;
        });
    }

    /** On a case both sit on, the survivor keeps one row, with the more significant role. */
    private function promoteCaseRoles(Contact $survivor, Contact $duplicate): void
    {
        $rank = array_flip(array_column(CaseContactRole::cases(), 'value'));
        $held = DB::table('case_contacts')->where('contact_id', $survivor->id)->get(['id', 'case_id', 'role'])->groupBy('case_id');

        DB::table('case_contacts')
            ->where('contact_id', $duplicate->id)
            ->whereIn('case_id', $held->keys())
            ->get(['id', 'case_id', 'role'])
            ->each(function ($row) use ($held, $rank) {
                $best = $held[$row->case_id]->sortBy(fn ($kept) => $rank[$kept->role])->first();

                if ($rank[$row->role] < $rank[$best->role]) {
                    DB::table('case_contacts')->where('id', $row->id)->delete();
                    DB::table('case_contacts')->where('id', $best->id)->update(['role' => $row->role]);
                }
            });
    }

    /** @param list<string> $scope */
    private function dropDuplicatesHeldBySurvivor(string $table, string $column, array $scope, Contact $survivor, Contact $duplicate): void
    {
        DB::table($table)
            ->where($column, $duplicate->id)
            ->whereExists(function ($kept) use ($table, $column, $scope, $survivor) {
                $kept->from("{$table} as kept")->where("kept.{$column}", $survivor->id);

                foreach ($scope as $key) {
                    $kept->whereColumn("kept.{$key}", "{$table}.{$key}");
                }
            })
            ->delete();
    }

    private function moveNotifications(Contact $survivor, Contact $duplicate): void
    {
        DB::table('notification_preferences')
            ->where('notifiable_type', Contact::class)
            ->where('notifiable_id', $duplicate->id)
            ->whereExists(fn ($kept) => $kept->from('notification_preferences as kept')
                ->where('kept.notifiable_type', Contact::class)
                ->where('kept.notifiable_id', $survivor->id)
                ->whereColumn('kept.notification_class', 'notification_preferences.notification_class')
                ->whereColumn('kept.channel', 'notification_preferences.channel'))
            ->delete();

        foreach (['notification_preferences', 'in_app_notifications'] as $table) {
            DB::table($table)
                ->where('notifiable_type', Contact::class)
                ->where('notifiable_id', $duplicate->id)
                ->update(['notifiable_id' => $survivor->id]);
        }
    }

    private function rewriteAutomationRuns(Contact $survivor, Contact $duplicate): void
    {
        DB::table('automation_runs')
            ->whereJsonContains('contact_ids', $duplicate->id)
            ->get(['id', 'contact_ids'])
            ->each(fn ($run) => DB::table('automation_runs')->where('id', $run->id)->update([
                'contact_ids' => json_encode(collect(json_decode($run->contact_ids))
                    ->map(fn (int $id) => $id === $duplicate->id ? $survivor->id : $id)
                    ->unique()->values()->all()),
            ]));
    }
}
