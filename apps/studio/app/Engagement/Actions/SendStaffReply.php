<?php

namespace App\Engagement\Actions;

use App\Engagement\Models\Message;
use App\Engagement\Notifications\StaffReplyPosted;
use App\Engagement\Support\MessageIdentifier;
use App\Notifications\NotificationService;
use App\Settings\Support\StudioSettingsResolver;
use Throwable;

/**
 * E-11: a staff reply leaves the building.
 *
 * Called by PostMessage immediately after the row lands, so "a staff mail
 * sent from a project is a Message row" is true by construction rather than
 * by a second log table somebody has to reconcile with the thread.
 *
 * Goes through NotificationService, E-07's single fan-out choke point —
 * never Mail:: or ->notify() directly — so "why did nobody get notified"
 * keeps having exactly one place to look.
 *
 * A failed send marks the row `failed` and does not throw. The message was
 * genuinely written and genuinely belongs on the thread; losing it because
 * an SMTP host was briefly down would be a worse answer than a visible
 * delivery state next to it.
 */
class SendStaffReply
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioSettingsResolver $settings,
    ) {}

    public function __invoke(Message $message): void
    {
        $recipients = $message->thread->participants()
            ->whereNotNull('contact_id')
            ->with('contact')
            ->get()
            ->pluck('contact')
            ->filter(fn ($contact) => $contact !== null && filled($contact->email));

        if ($recipients->isEmpty()) {
            return;
        }

        $message->forceFill(['email_message_id' => MessageIdentifier::for($message)])->save();

        try {
            foreach ($recipients as $contact) {
                $this->notifications->send($contact, new StaffReplyPosted($message, $this->settings));
            }

            $message->forceFill(['delivery_state' => 'sent'])->save();
        } catch (Throwable $e) {
            // Visible in the conversation rather than in a log: a dead
            // address is something the studio needs to see where it is about
            // to type its next reply.
            $message->forceFill(['delivery_state' => 'failed'])->save();

            report($e);
        }
    }
}
