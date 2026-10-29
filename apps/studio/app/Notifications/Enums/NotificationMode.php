<?php

namespace App\Notifications\Enums;

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

    /** @return list<string> */
    public static function allowedFor(bool $briefable): array
    {
        return array_values(array_filter(
            array_map(fn (self $mode) => $mode->value, self::cases()),
            fn (string $mode) => $mode !== self::Briefing->value || $briefable,
        ));
    }

    /**
     * What a staff member gets before touching the pane: the notification's
     * own defaultMode() where it declares one. The kernel cannot name the
     * domain's notifications, so each one says this for itself.
     */
    public static function defaultFor(string $notificationClass): self
    {
        return method_exists($notificationClass, 'defaultMode')
            ? $notificationClass::defaultMode()
            : self::Immediate;
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
