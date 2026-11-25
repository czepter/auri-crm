<?php

namespace App\Settings\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\LiveUpdate;
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

    /** One pair per client visit; reachable through the event filter. */
    private const NOISE = ['portal.link_issued', 'portal.link_consumed'];

    /** Hidden until asked for: every sign-in of every staff member. */
    private const LOGINS = ['staff.login.succeeded', 'staff.login.mfa_required'];

    public function index(Request $request): Response
    {
        abort_unless(auth('staff')->user()?->role === StaffRole::Admin, 404);

        $filters = $request->validate([
            'ereignis' => ['nullable', 'string'],
            'person' => ['nullable', 'string'],
            'von' => ['nullable', 'date_format:Y-m-d'],
            'bis' => ['nullable', 'date_format:Y-m-d'],
            'anmeldungen' => ['nullable', 'boolean'],
        ]);

        $zone = StudioSettings::current()->localTimezone();

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->when(
                $filters['ereignis'] ?? null,
                fn ($query, $event) => $query->where('event', $event),
                fn ($query) => $query->whereNotIn('event', $request->boolean('anmeldungen') ? self::NOISE : [...self::NOISE, ...self::LOGINS]),
            )
            ->when($filters['person'] ?? null, fn ($query, $person) => $query
                ->whereIn('actor_id', User::query()->where('public_id', $person)->select('id')))
            ->when($filters['von'] ?? null, fn ($query, $day) => $query
                ->where('created_at', '>=', Carbon::parse($day, $zone)->startOfDay()))
            ->when($filters['bis'] ?? null, fn ($query, $day) => $query
                ->where('created_at', '<=', Carbon::parse($day, $zone)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        $projects = ClientCase::query()->whereIn('id', $logs->pluck('case_id')->filter())->get()->keyBy('id');
        $contacts = Contact::query()->whereIn('public_id', $logs->pluck('subject')->filter())->get()->keyBy('public_id');

        return Inertia::render('Settings/AuditLog', [
            'entries' => LiveUpdate::live($logs->map(fn (AuditLog $log) => [
                'id' => $log->public_id,
                'at' => $log->created_at->toIso8601String(),
                'actor' => $log->actor?->name,
                'event' => $log->event,
                'label' => self::label($log->event),
                'detail' => $log->event === 'studio_settings.updated' ? self::settingsDetail($log->context ?? []) : [],
                'target' => match (true) {
                    $projects->has($log->case_id) => [
                        'name' => $projects[$log->case_id]->name,
                        'href' => "/auftraege/{$projects[$log->case_id]->public_id}",
                    ],
                    $contacts->has($log->subject) => [
                        'name' => $contacts[$log->subject]->name,
                        'href' => "/kontakte/{$contacts[$log->subject]->public_id}",
                    ],
                    default => null,
                },
            ])->all()),
            'nextCursor' => LiveUpdate::live($logs->nextCursor()?->encode()),
            'filters' => [
                'ereignis' => $filters['ereignis'] ?? '',
                'person' => $filters['person'] ?? '',
                'von' => $filters['von'] ?? '',
                'bis' => $filters['bis'] ?? '',
                'anmeldungen' => $request->boolean('anmeldungen'),
            ],
            'events' => LiveUpdate::live(AuditLog::query()->distinct()->pluck('event')
                ->map(fn (string $event) => ['value' => $event, 'label' => self::label($event)])
                ->sortBy('label')
                ->values()
                ->all()),
            'people' => LiveUpdate::live(User::query()->orderBy('name')->get(['public_id', 'name'])
                ->map(fn (User $user) => ['value' => $user->public_id, 'label' => $user->name])
                ->all()),
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
                ? "{$label}: ".self::value($changes[$key][0], $key).' → '.self::value($changes[$key][1], $key)
                : $label;
        }

        return $lines;
    }

    private static function value(mixed $value, string $key = ''): string
    {
        if (is_string($value) && str_ends_with($key, '_family') && $value !== '') {
            $value = DocumentTemplate::query()->where('family_id', $value)->orderByDesc('version')->value('name') ?? $value;
        }

        if (is_string($value) && strlen($value) > 4 && (preg_match('/iban|token|secret|_key$|password/i', $key) || preg_match('/^[A-Z]{2}\d{2}(\s?[A-Z0-9]{4}){2,7}\s?[A-Z0-9]{1,4}$/', $value))) {
            return '… '.substr($value, -4);
        }

        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'Ja' : 'Nein',
            is_string($value) && trans()->has("audit.values.{$value}") => __("audit.values.{$value}"),
            $value === [] => '—',
            is_array($value) && $key === 'retention_policy' => implode(', ', array_map(fn ($part) => __("audit.parts.{$part}"), $value)),
            is_array($value) && $key === 'working_days' => implode(', ', array_map(fn ($day) => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][$day - 1], $value)),
            is_array($value) => implode(', ', array_map(fn ($item) => self::value($item), $value)),
            default => Str::limit(strip_tags((string) $value), 60),
        };
    }
}
