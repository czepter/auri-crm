<?php

namespace App\Gallery\Support;

/**
 * Was Kunde und Gäste in einer Galerie tun dürfen. Gespeichert als
 * {client: {...}, guest: {...}}; fehlende Schlüssel fallen auf die Vorgabe,
 * Ausblenden gibt es für Gäste nie.
 */
final class GalleryPermissions
{
    public const ACTIONS = ['favorites', 'comments', 'hide', 'share'];

    public const AUDIENCES = ['client', 'guest'];

    /** @return array<string, array<string, bool>> */
    public static function defaults(): array
    {
        return [
            'client' => ['favorites' => true, 'comments' => true, 'hide' => true, 'share' => true],
            'guest' => ['favorites' => true, 'comments' => false, 'hide' => false, 'share' => true],
        ];
    }

    /** @return array<string, array<string, bool>> */
    public static function normalize(?array $stored): array
    {
        $permissions = self::defaults();

        foreach (self::AUDIENCES as $audience) {
            foreach (self::ACTIONS as $action) {
                if (isset($stored[$audience][$action])) {
                    $permissions[$audience][$action] = (bool) $stored[$audience][$action];
                }
            }
        }

        $permissions['guest']['hide'] = false;

        return $permissions;
    }

    /**
     * Die alten Schalter lists_enabled / comments_enabled setzen eine Aktion
     * für beide Gruppen: aus schaltet sie ab, an stellt die Vorgabe wieder her.
     *
     * @return array<string, array<string, bool>>
     */
    public static function withLegacy(array $permissions, string $action, bool $enabled): array
    {
        foreach (self::AUDIENCES as $audience) {
            $permissions[$audience][$action] = $enabled && self::defaults()[$audience][$action];
        }

        return $permissions;
    }

    /** @return array<string, array<int, string>> */
    public static function rules(string $key = 'permissions'): array
    {
        $rules = [$key => ['sometimes', 'array']];

        foreach (self::AUDIENCES as $audience) {
            foreach (self::ACTIONS as $action) {
                $rules["{$key}.{$audience}.{$action}"] = ['sometimes', 'boolean'];
            }
        }

        return $rules;
    }
}
