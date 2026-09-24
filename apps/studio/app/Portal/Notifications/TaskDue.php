<?php

namespace App\Portal\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Models\Contact;
use App\Crm\Models\FormInstance;
use App\Crm\Models\Task;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * T-28: the client's own open task. Unlike an automation rule, it hangs off
 * one task and stops the moment that task is ticked.
 *
 * The link goes to the place the task is done, not to the front page: a
 * reminder that only says something is open moves the search to the reader.
 * Where the project has exactly one shared questionnaire, that is the place;
 * otherwise the project's own portal screen, which carries the questionnaire,
 * the gallery and the payments in one list.
 */
class TaskDue extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Task $task,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->task->title)
            ->greeting('Hallo,')
            ->line("Offen zu {$this->task->case->name}: {$this->task->title}.")
            ->action('Im Kundenbereich öffnen', $this->target());

        if ($notifiable instanceof Contact) {
            $message->line(
                '[Benachrichtigungen abbestellen]('
                .config('portal.url')."/abmelden/{$notifiable->public_id}".')'
            );
        }

        return $this->appendStudioMailIdentity($message, $this->settings);
    }

    private function target(): string
    {
        $project = $this->task->case;
        $base = config('portal.url')."/projekte/{$project->public_id}";

        $questionnaires = FormInstance::query()
            ->where('case_id', $project->id)
            ->shared()
            ->get();

        return $questionnaires->count() === 1
            ? "{$base}/fragebogen/{$questionnaires->first()->public_id}"
            : $base;
    }
}
