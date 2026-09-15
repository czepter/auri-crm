<?php

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationTriggerType;
use App\Crm\Events\ProjectBooked;

/**
 * E-09: the default AutomationRule set, shipped as config rather than a
 * migration so a tenant's rules can diverge (edited or deactivated) without
 * a schema change. App\Automation\Console\Commands\
 * SeedDefaultAutomationRulesCommand reads this list and creates one
 * AutomationRule per entry, matched on 'key' — re-running it never
 * duplicates a row and never overwrites a tenant's edit to one already seeded.
 *
 * Each entry's shape matches App\Automation\Models\AutomationRule::booted():
 * either event_class alone, or anchor/offset_days/direction alone. A text a
 * client reads is keyed formal/informal (#439); the command seeds the one
 * matching the studio's register.
 */
return [
    'rules' => [
        [
            'key' => 'shoot-reminder-7-days-before',
            'name' => 'Shooting-Erinnerung — 7 Tage vorher',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PerShoot,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => [
                'formal' => 'Ihr Shooting steht bald an',
                'informal' => 'Dein Shooting steht bald an',
            ],
            'notification_body' => [
                'formal' => 'Ihr Shooting steht bald an, und wir freuen uns schon darauf. Wenn vorher noch etwas zu klären ist, melden Sie sich gern.',
                'informal' => 'Dein Shooting steht bald an, und wir freuen uns schon darauf. Wenn vorher noch etwas zu klären ist, melde dich gern.',
            ],
        ],
        [
            'key' => 'quote-follow-up',
            'name' => 'Angebot — Erinnerung vor Ablauf',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::QuoteValidity,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => [
                'formal' => 'Ihr Angebot läuft bald ab',
                'informal' => 'Dein Angebot läuft bald ab',
            ],
            'notification_body' => [
                'formal' => 'Ihr Angebot gilt bis zum {{ quote.validUntil }}. Melden Sie sich gern, wenn etwas offen ist.',
                'informal' => 'Dein Angebot gilt bis zum {{ quote.validUntil }}. Melde dich gern, wenn etwas offen ist.',
            ],
        ],
        // E-14: the studio's own reminder, off until somebody wants it —
        // there so it can be found rather than invented.
        [
            'key' => 'crew-briefing-day-before',
            'name' => 'Crew — Tag vor dem Shooting',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PerShoot,
            'offset_days' => 1,
            'direction' => AutomationOffsetDirection::Before,
            'recipient_type' => AutomationRecipient::Crew,
            'is_active' => false,
            'notification_subject' => 'Shooting: {{ project.name }}',
            'notification_body' => 'Das Shooting für {{ project.name }} steht an. Ausrüstung packen, Ablauf durchgehen.',
        ],
        [
            'key' => 'booking-confirmation',
            'name' => 'Buchungsbestätigung',
            'trigger_type' => AutomationTriggerType::Event,
            'event_class' => ProjectBooked::class,
            'notification_subject' => [
                'formal' => 'Ihre Buchung ist bestätigt',
                'informal' => 'Deine Buchung ist bestätigt',
            ],
            'notification_body' => [
                'formal' => 'Wir freuen uns auf Ihr Projekt und melden uns rechtzeitig mit den nächsten Schritten.',
                'informal' => 'Wir freuen uns auf dein Projekt und melden uns rechtzeitig mit den nächsten Schritten.',
            ],
        ],
    ],
];
