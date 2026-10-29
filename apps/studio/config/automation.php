<?php

use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationCondition;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationTriggerType;
use App\Crm\Enums\ProjectType;
use App\Crm\Events\CaseBooked;

/**
 * E-09: the default AutomationRule set, shipped as config rather than a
 * migration so a tenant's rules can diverge (edited or deactivated) without
 * a schema change. App\Automation\Actions\SeedDefaultAutomationRules reads
 * this list and creates one AutomationRule per entry, matched on 'key' —
 * re-running it never duplicates a row and never overwrites a tenant's edit.
 *
 * Each entry's shape matches App\Automation\Models\AutomationRule::booted():
 * either event_class alone, or anchor/offset_days/direction alone. A text a
 * client reads is keyed formal/informal (#439); the seeder picks the one
 * matching the studio's register. Informal wedding texts address the couple.
 *
 * #438: client rules land as drafts in the Posteingang, so a text here is a
 * starting point the studio edits before it goes out.
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
        [
            'key' => 'quote-no-reply',
            'name' => 'Angebot — ohne Antwort',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::QuoteSent,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::After,
            'notification_subject' => [
                'formal' => 'Ihr Angebot für {{ project.name }}',
                'informal' => 'Dein Angebot für {{ project.name }}',
            ],
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nist unser Angebot gut bei Ihnen angekommen? Wenn Sie Fragen haben oder etwas anders zusammengestellt haben möchten, melden Sie sich gern. Es gilt bis zum {{ quote.validUntil }}.",
                'informal' => "Hallo {{ contact.name }},\n\nist unser Angebot gut bei dir angekommen? Wenn du Fragen hast oder etwas anders zusammengestellt haben möchtest, melde dich gern. Es gilt bis zum {{ quote.validUntil }}.",
            ],
        ],
        [
            'key' => 'questionnaire-before-wedding',
            'name' => 'Ablauf-Fragebogen — vor der Hochzeit',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PrimaryShoot,
            'offset_days' => 30,
            'direction' => AutomationOffsetDirection::Before,
            'condition' => AutomationCondition::QuestionnaireOpen,
            'project_type' => ProjectType::Wedding->value,
            'notification_subject' => [
                'formal' => 'Ihr Fragebogen zum Ablauf',
                'informal' => 'Euer Fragebogen zum Ablauf',
            ],
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nIhre Hochzeit am {{ shoot.date }} rückt näher, und wir freuen uns sehr darauf.\n\nIm Kundenbereich wartet noch der Fragebogen zum Ablauf auf Ihre Angaben.",
                'informal' => "Hallo {{ contact.name }},\n\neure Hochzeit am {{ shoot.date }} rückt näher, und wir freuen uns sehr darauf.\n\nIm Kundenbereich wartet noch der Fragebogen zum Ablauf auf eure Angaben.",
            ],
        ],
        [
            'key' => 'installment-due-soon',
            'name' => 'Rate — vor Fälligkeit',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::InstallmentDue,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => 'Nächste Rate für {{ project.name }}',
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nam {{ invoice.dueOn }} wird die nächste Rate für {{ project.name }} fällig: {{ invoice.gross }}.\n\nUnsere Bankverbindung: {{ studio.bankHolder }}, IBAN {{ studio.bankIban }}",
                'informal' => "Hallo {{ contact.name }},\n\nam {{ invoice.dueOn }} wird die nächste Rate für {{ project.name }} fällig: {{ invoice.gross }}.\n\nUnsere Bankverbindung: {{ studio.bankHolder }}, IBAN {{ studio.bankIban }}",
            ],
        ],
        [
            'key' => 'gallery-expiry-soon',
            'name' => 'Galerie — vor Ablauf',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::GalleryExpiry,
            'offset_days' => 14,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => [
                'formal' => 'Ihre Galerie {{ gallery.title }}',
                'informal' => 'Deine Galerie {{ gallery.title }}',
            ],
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nIhre Galerie ist noch bis zum {{ gallery.expiresOn }} online. Wenn Sie Bilder herunterladen möchten, finden Sie sie hier: {{ gallery.link }}",
                'informal' => "Hallo {{ contact.name }},\n\ndeine Galerie ist noch bis zum {{ gallery.expiresOn }} online. Wenn du Bilder herunterladen möchtest, findest du sie hier: {{ gallery.link }}",
            ],
        ],
        [
            'key' => 'review-request',
            'name' => 'Bewertungsbitte',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::Delivered,
            'offset_days' => 14,
            'direction' => AutomationOffsetDirection::After,
            'notification_subject' => [
                'formal' => 'Wie gefallen Ihnen Ihre Bilder?',
                'informal' => 'Wie gefallen dir deine Bilder?',
            ],
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nes hat uns viel Freude gemacht, {{ project.name }} zu fotografieren.\n\nWenn Sie mögen, schreiben Sie uns ein paar Sätze dazu, wie Sie die Zusammenarbeit erlebt haben. Darüber freuen wir uns sehr.",
                'informal' => "Hallo {{ contact.name }},\n\nes hat uns viel Freude gemacht, {{ project.name }} zu fotografieren.\n\nWenn du magst, schreib uns ein paar Sätze dazu, wie du die Zusammenarbeit erlebt hast. Darüber freuen wir uns sehr.",
            ],
        ],
        [
            'key' => 'album-print-hint',
            'name' => 'Album und Abzüge',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::Delivered,
            'offset_days' => 30,
            'direction' => AutomationOffsetDirection::After,
            'notification_subject' => [
                'formal' => 'Ihre Bilder auf Papier',
                'informal' => 'Deine Bilder auf Papier',
            ],
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nmanche Bilder aus {{ project.name }} wirken auf Papier noch einmal ganz anders als am Bildschirm. Wenn Sie über ein Album oder Abzüge nachdenken, schreiben Sie uns, dann stellen wir gemeinsam eine Auswahl zusammen.",
                'informal' => "Hallo {{ contact.name }},\n\nmanche Bilder aus {{ project.name }} wirken auf Papier noch einmal ganz anders als am Bildschirm. Wenn du über ein Album oder Abzüge nachdenkst, schreib uns, dann stellen wir gemeinsam eine Auswahl zusammen.",
            ],
        ],
        [
            'key' => 'wedding-anniversary',
            'name' => 'Hochzeitstag',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PrimaryShoot,
            'offset_days' => 365,
            'direction' => AutomationOffsetDirection::After,
            'project_type' => ProjectType::Wedding->value,
            'notification_subject' => 'Alles Gute zum Hochzeitstag',
            'notification_body' => [
                'formal' => "Guten Tag {{ contact.name }},\n\nzu Ihrem Hochzeitstag wünschen wir Ihnen von Herzen alles Gute. Wir denken gern an den {{ shoot.date }} zurück.",
                'informal' => "Hallo {{ contact.name }},\n\nzu eurem Hochzeitstag wünschen wir euch von Herzen alles Gute. Wir denken gern an den {{ shoot.date }} zurück.",
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
            'key' => 'packing-list',
            'name' => 'Packliste',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PerShoot,
            'offset_days' => 3,
            'direction' => AutomationOffsetDirection::Before,
            'recipient_type' => AutomationRecipient::Studio,
            'action' => AutomationAction::Task,
            'notification_subject' => 'Packliste: {{ project.name }}',
            'notification_body' => 'Ausrüstung für {{ project.name }} zusammenstellen.',
        ],
        [
            'key' => 'archive-reminder',
            'name' => 'Archiv-Erinnerung',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::Delivered,
            'offset_days' => 90,
            'direction' => AutomationOffsetDirection::After,
            'recipient_type' => AutomationRecipient::Studio,
            'action' => AutomationAction::Task,
            'notification_subject' => 'Archivieren: {{ project.name }}',
            'notification_body' => 'Daten zu {{ project.name }} sichern und das Projekt archivieren.',
        ],
        [
            'key' => 'inquiry-unanswered',
            'name' => 'Anfrage unbeantwortet',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::InquiryCaptured,
            'offset_days' => 2,
            'direction' => AutomationOffsetDirection::After,
            'recipient_type' => AutomationRecipient::Studio,
            'notification_subject' => 'Anfrage offen: {{ project.name }}',
            'notification_body' => 'Die Anfrage zu {{ project.name }} steht noch im Eingang.',
        ],
        [
            'key' => 'booking-confirmation',
            'name' => 'Buchungsbestätigung',
            'trigger_type' => AutomationTriggerType::Event,
            'event_class' => CaseBooked::class,
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
