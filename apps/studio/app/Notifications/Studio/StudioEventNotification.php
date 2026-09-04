<?php

namespace App\Notifications\Studio;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Models\Project;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What the studio is told about, as opposed to what a client is told about.
 * Every subclass is one row on the profile screen's Benachrichtigungen pane:
 * the preference is keyed on the class name, so the event and the checkbox
 * that silences it are the same thing.
 *
 * Two channels on purpose, and the pane's three-way choice is which of them
 * survive: `sofort` leaves both on, `Zusammenfassung` turns mail off and
 * keeps in_app — the in-app row is what the digest is later built from — and
 * `aus` turns both off. Nothing else in the system needs to know about the
 * digest, because a digested event is an in-app row like any other.
 *
 * The settings resolver is pulled from the container rather than passed in:
 * every one of these is raised by the same fan-out (NotifyStudio), which has
 * no reason to know about the studio's mail identity.
 */
abstract class StudioEventNotification extends Notification
{
    use AppliesStudioMailIdentity;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'in_app'];
    }

    /** The one-line subject, and the title of the in-app row. */
    abstract public function title(): string;

    /** What happened, named once. */
    abstract public function body(): string;

    /** The admin screen that answers it. */
    abstract public function url(): string;

    /**
     * Whose desk it belongs on. NotifyStudio narrows to this project's crew
     * where there is one — null means the whole studio, which is also what a
     * project with nobody crewed on it comes to.
     */
    abstract public function project(): ?Project;

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title())
            ->greeting("Hallo {$notifiable->name},")
            ->line($this->body())
            ->action('Ansehen', url($this->url()));

        return $this->appendStudioMailIdentity($message, app(StudioSettingsResolver::class));
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }
}
