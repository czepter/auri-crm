<?php

namespace App\Crm\Actions;

use App\Crm\Models\Contact;
use App\Crm\Support\ContactCsv;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportContacts
{
    public function __construct(private readonly CreateContact $createContact) {}

    /**
     * Each row is created, skipped because its address is already a contact
     * ('existing') or appears earlier in the file ('repeated'), or invalid.
     *
     * @param  list<list<string>>  $rows
     * @param  list<string>  $mapping
     * @param  array<string, mixed>  $rules  what a contact must satisfy, as ContactController states it
     * @return list<array{line: int, name: string, email: ?string, status: 'create'|'existing'|'repeated'|'invalid', reason: ?string, attributes: array<string, mixed>}>
     */
    public function plan(array $rows, array $mapping, array $rules): array
    {
        $seen = Contact::query()->whereNotNull('email')->pluck('email')
            ->mapWithKeys(fn (string $email) => [mb_strtolower($email) => 'existing'])->all();

        return collect($rows)->map(function (array $row, int $index) use ($mapping, $rules, &$seen) {
            $attributes = ContactCsv::attributes($row, $mapping);
            $validator = Validator::make($attributes, $rules);

            $key = mb_strtolower((string) $attributes['email']);
            $status = match (true) {
                $validator->fails() => 'invalid',
                $key !== '' && isset($seen[$key]) => $seen[$key] === 'existing' ? 'existing' : 'repeated',
                default => 'create',
            };

            if ($status === 'create' && $key !== '') {
                $seen[$key] = 'repeated';
            }

            return [
                'line' => $index + 2,
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'status' => $status,
                'reason' => $status === 'invalid' ? $validator->errors()->first() : null,
                'attributes' => $attributes,
            ];
        })->all();
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $mapping
     * @param  array<string, mixed>  $rules
     */
    public function __invoke(array $rows, array $mapping, array $rules, User $actor): int
    {
        $plan = collect($this->plan($rows, $mapping, $rules));
        $create = $plan->where('status', 'create');

        DB::transaction(fn () => $create->each(fn (array $row) => ($this->createContact)($row['attributes'])));

        AuditLogger::record(
            event: 'contact.imported',
            actor: $actor,
            context: [
                'created' => $create->count(),
                'skipped' => $plan->whereIn('status', ['existing', 'repeated'])->count(),
                'invalid' => $plan->where('status', 'invalid')->count(),
            ],
        );

        return $create->count();
    }
}
