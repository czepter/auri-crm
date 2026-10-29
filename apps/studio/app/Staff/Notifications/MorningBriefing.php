<?php

namespace App\Staff\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * T-23: what today looks like, in one mail instead of four.
 *
 * Not a row in StudioEvents::ALL — the briefing is not an event, it is the
 * way three of them travel. Not Transactional either: it is exactly the kind
 * of mail quiet hours exist for.
 *
 * @param  array<string, list<string>>  $sections  section heading => its lines, empty ones already dropped
 */
class MorningBriefing extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(private readonly array $sections) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Tagesüberblick')
            ->greeting("Hallo {$notifiable->name},");

        foreach ($this->sections as $heading => $lines) {
            $message->line("**{$heading}**");

            foreach ($lines as $line) {
                $message->line($line);
            }
        }

        $message->action('Zur Übersicht', url('/uebersicht'));

        return $this->appendStudioMailIdentity($message);
    }
}
