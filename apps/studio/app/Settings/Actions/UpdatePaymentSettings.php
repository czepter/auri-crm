<?php

namespace App\Settings\Actions;

use App\Models\StudioSettings;
use App\Models\User;
use App\Support\AuditLogger;

class UpdatePaymentSettings
{
    /**
     * T-07's own pane, saved in one submit like Studio-Profil (T-02) and
     * unlike Zahlungsdaten & USt's own endpoint — nothing here needs the
     * "own fieldset, own button" separation those two have, because every
     * field on this pane lives or dies with the same "Zahlungen" save.
     *
     * The four key columns are written only when a NEW, non-empty value is
     * given — the write-only form (never echoing a stored key back) has no
     * other way to say "leave this pair alone", and an empty string arriving
     * from an untouched field must never null out a key that already works.
     * Audited by column name only: a key's value never rides along into
     * AuditLog, the same "never leaves the cast that decrypts it" rule that
     * keeps it out of every Inertia prop and API response.
     *
     * @param  array{
     *     payments_stripe_enabled: bool,
     *     payments_stripe_mode: string,
     *     payments_stripe_test_publishable_key?: ?string,
     *     payments_stripe_test_secret_key?: ?string,
     *     payments_stripe_live_publishable_key?: ?string,
     *     payments_stripe_live_secret_key?: ?string,
     * }  $attributes
     */
    public function __invoke(array $attributes, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();

        $plainKeys = ['payments_stripe_enabled', 'payments_stripe_mode'];
        $keyColumns = [
            'payments_stripe_test_publishable_key', 'payments_stripe_test_secret_key',
            'payments_stripe_live_publishable_key', 'payments_stripe_live_secret_key',
        ];

        $updates = [];
        $changedNames = [];
        $plainChanges = [];

        foreach ($plainKeys as $key) {
            if ($attributes[$key] !== $row->{$key}) {
                $plainChanges[$key] = [$row->{$key}, $attributes[$key]];
                $updates[$key] = $attributes[$key];
                $changedNames[] = $key;
            }
        }

        foreach ($keyColumns as $column) {
            if (! empty($attributes[$column] ?? null)) {
                $updates[$column] = $attributes[$column];
                $changedNames[] = $column;
            }
        }

        if ($updates === []) {
            return;
        }

        $row->update($updates);

        StudioSettings::forgetCurrent();

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['keys' => $changedNames, 'changes' => $plainChanges],
            subject: $row->public_id,
        );
    }
}
