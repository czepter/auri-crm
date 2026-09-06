<?php

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationOffsetDirection;
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
 * either event_class alone, or anchor/offset_days/direction alone.
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
            'notification_subject' => 'Ihr Shooting steht bald an',
            'notification_body' => 'Nur eine kurze Erinnerung: Ihr Shooting findet in 7 Tagen statt.',
        ],
        [
            'key' => 'thank-you-after-wedding',
            'name' => 'Dankeschön — nach der Hochzeit',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PrimaryShoot,
            'offset_days' => 1,
            'direction' => AutomationOffsetDirection::After,
            'notification_subject' => 'Danke, dass wir dabei sein durften',
            'notification_body' => 'Vielen Dank für die wunderschöne Feier — es war uns eine Freude.',
        ],
        [
            'key' => 'quote-follow-up',
            'name' => 'Angebot — Erinnerung vor Ablauf',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::QuoteValidity,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => 'Ihr Angebot läuft bald ab',
            'notification_body' => 'Ihr Angebot gilt noch eine Woche. Melden Sie sich gern, wenn etwas offen ist.',
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
            'notification_subject' => 'Morgen: {{ project.name }}',
            'notification_body' => 'Morgen steht {{ project.name }} an. Ausrüstung packen, Ablauf durchgehen.',
        ],
        [
            'key' => 'booking-confirmation',
            'name' => 'Buchungsbestätigung',
            'trigger_type' => AutomationTriggerType::Event,
            'event_class' => ProjectBooked::class,
            'notification_subject' => 'Ihre Buchung ist bestätigt',
            'notification_body' => 'Wir freuen uns auf Ihr Projekt und melden uns rechtzeitig mit den nächsten Schritten.',
        ],
    ],
];
