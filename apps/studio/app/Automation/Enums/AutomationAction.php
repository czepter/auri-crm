<?php

namespace App\Automation\Enums;

use App\Crm\Enums\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * #438: what a rule does once it fires. Toward the client only a draft that
 * waits for approval; toward the studio a notice or a task.
 * AutomationRule::booted() holds that split.
 */
#[TypeScript]
enum AutomationAction: string implements HasLabel
{
    case Notice = 'notice';
    case Task = 'task';
    case MailDraft = 'mail_draft';

    public function label(): string
    {
        return match ($this) {
            self::Notice => 'Hinweis',
            self::Task => 'Aufgabe',
            self::MailDraft => 'Entwurf',
        };
    }
}
