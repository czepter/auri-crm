<?php

namespace App\Settings\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of audit_logs for the owner. `context` is never sent: it can
 * carry personal data. The one exception is a settings change, where old and
 * new value are the whole point of the row.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, StudioSettingsResolver $settings): Response
    {
        abort_unless(auth('staff')->user()?->role === StaffRole::Admin, 404);

        $filters = $request->validate([
            'ereignis' => ['nullable', 'string'],
            'person' => ['nullable', 'string'],
            'von' => ['nullable', 'date_format:Y-m-d'],
            'bis' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $zone = $settings->timezone();

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->when($filters['ereignis'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['person'] ?? null, fn ($query, $person) => $query
                ->whereIn('actor_id', User::query()->where('public_id', $person)->select('id')))
            ->when($filters['von'] ?? null, fn ($query, $day) => $query
                ->where('created_at', '>=', Carbon::parse($day, $zone)->startOfDay()))
            ->when($filters['bis'] ?? null, fn ($query, $day) => $query
                ->where('created_at', '<=', Carbon::parse($day, $zone)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        $projects = Project::query()->whereIn('id', $logs->pluck('project_id')->filter())->get()->keyBy('id');
        $contacts = Contact::query()->whereIn('public_id', $logs->pluck('subject')->filter())->get()->keyBy('public_id');

        return Inertia::render('Settings/AuditLog', [
            'entries' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->public_id,
                'at' => $log->created_at->toIso8601String(),
                'actor' => $log->actor?->name,
                'label' => self::label($log->event),
                'detail' => $log->event === 'studio_settings.updated' ? self::settingsDetail($log->context ?? []) : [],
                'target' => match (true) {
                    $projects->has($log->project_id) => [
                        'name' => $projects[$log->project_id]->name,
                        'href' => "/auftraege/{$projects[$log->project_id]->public_id}",
                    ],
                    $contacts->has($log->subject) => [
                        'name' => $contacts[$log->subject]->name,
                        'href' => "/kontakte/{$contacts[$log->subject]->public_id}",
                    ],
                    default => null,
                },
            ])->all(),
            'nextCursor' => $logs->nextCursor()?->encode(),
            'filters' => [
                'ereignis' => $filters['ereignis'] ?? '',
                'person' => $filters['person'] ?? '',
                'von' => $filters['von'] ?? '',
                'bis' => $filters['bis'] ?? '',
            ],
            'events' => AuditLog::query()->distinct()->pluck('event')
                ->map(fn (string $event) => ['value' => $event, 'label' => self::label($event)])
                ->sortBy('label')
                ->values()
                ->all(),
            'people' => User::query()->orderBy('name')->get(['public_id', 'name'])
                ->map(fn (User $user) => ['value' => $user->public_id, 'label' => $user->name])
                ->all(),
        ]);
    }

    /** A row written before its event was named here still shows something. */
    private static function label(string $event): string
    {
        return trans()->has("audit.{$event}") ? __("audit.{$event}") : $event;
    }

    /** @return list<string> */
    private static function settingsDetail(array $context): array
    {
        $changes = isset($context['key'])
            ? [$context['key'] => [$context['old'] ?? null, $context['new'] ?? null]]
            : ($context['changes'] ?? []);

        $lines = [];

        foreach ($context['keys'] ?? array_keys($changes) as $key) {
            $label = trans()->has("audit.fields.{$key}") ? __("audit.fields.{$key}") : $key;

            $lines[] = isset($changes[$key])
                ? "{$label}: ".self::value($changes[$key][0]).' → '.self::value($changes[$key][1])
                : $label;
        }

        return $lines;
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'Ja' : 'Nein',
            is_array($value) => implode(', ', $value),
            default => Str::limit(strip_tags((string) $value), 60),
        };
    }
}
