<?php

namespace App\Notifications\Studio;

use App\Concerns\AppliesStudioMailIdentity;
use App\Notifications\Contracts\Transactional;
use App\Notifications\Models\InAppNotification;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * What happened since the last one, in one mail. Transactional: the per-event
 * preferences were already applied when each row was written — suppressing
 * the digest by them a second time would silence exactly the events it exists
 * to carry.
 *
 * @param  Collection<int, InAppNotification>  $rows
 */
class StudioDigest extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;

    public function __construct(private readonly Collection $rows) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Zusammenfassung — '.$this->rows->count().' Ereignisse')
            ->greeting("Hallo {$notifiable->name},");

        foreach ($this->rows as $row) {
            $message->line("{$row->data['title']}: {$row->data['body']}");
        }

        $message->action('Im Studio ansehen', url('/benachrichtigungen'));

        return $this->appendStudioMailIdentity($message, app(StudioSettingsResolver::class));
    }
}
