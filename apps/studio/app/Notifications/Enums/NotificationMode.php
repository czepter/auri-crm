<?php

namespace App\Notifications\Enums;

use App\Notifications\Studio;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-23: the one way an event reaches a person, stored rather than derived.
 *
 * Until this enum, "Zusammenfassung" was the inference *mail off, in-app on*.
 * "Im Tagesüberblick" is the same inference and would be indistinguishable
 * from it, so the choice moved into a column of its own. The four exclude
 * each other: an event travels one way, and a doubled arrival is now a
 * setting rather than a side effect.
 */
#[TypeScript]
enum NotificationMode: string
{
    case Immediate = 'immediate';
    case Briefing = 'briefing';
    case Digest = 'digest';
    case Off = 'off';

    /**
     * The three scan events, and only them. They describe states that are
     * still true in the morning; the other eight happen at a moment, and a
     * second collecting channel beside the summary would be the same
     * function under another name.
     *
     * @var list<class-string>
     */
    public const BRIEFABLE = [
        Studio\TasksDue::class,
        Studio\LeadStalled::class,
        Studio\InstallmentOverdue::class,
    ];

    /** @return list<string> */
    public static function allowedFor(string $notificationClass): array
    {
        return array_values(array_filter(
            array_map(fn (self $mode) => $mode->value, self::cases()),
            fn (string $mode) => $mode !== self::Briefing->value
                || in_array($notificationClass, self::BRIEFABLE, true),
        ));
    }

    /**
     * What a staff member gets before touching the pane. A due task and an
     * untouched inquiry are equally true at 07:00; an overdue installment is
     * money, and money does not wait half an hour.
     */
    public static function defaultFor(string $notificationClass): self
    {
        return match ($notificationClass) {
            Studio\TasksDue::class, Studio\LeadStalled::class => self::Briefing,
            default => self::Immediate,
        };
    }

    public function allows(string $channel): bool
    {
        return match ($this) {
            self::Immediate => true,
            self::Briefing, self::Digest => $channel !== 'mail',
            self::Off => false,
        };
    }
}
