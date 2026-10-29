<?php

namespace App\Staff\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Models\User;
use App\Notifications\Contracts\Transactional;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One class for both ends of a transfer: the giver and the receiver read different sentences. */
class StaffOwnershipTransferred extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly User $from,
        private readonly User $to,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studio = $this->settings->displayName() ?? 'das Studio';

        $message = (new MailMessage)
            ->subject('Inhaberschaft übertragen')
            ->greeting("Hallo {$notifiable->name},");

        $message = $notifiable->is($this->to)
            ? $message
                ->line("{$this->from->name} hat dir die Inhaberschaft für {$studio} übertragen. Damit verwaltest du Einstellungen, Team und Export.")
                ->line('Viel Erfolg als Inhaber.')
                ->action('Zum Studio', url('/'))
            : $message
                ->line("Du hast {$this->to->name} die Inhaberschaft für {$studio} übertragen und bist jetzt {$this->from->role->label()}.");

        return $this->appendStudioMailIdentity($message);
    }
}
