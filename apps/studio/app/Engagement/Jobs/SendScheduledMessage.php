<?php

namespace App\Engagement\Jobs;

use App\Engagement\Actions\SendStaffReply;
use App\Engagement\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * E-13: the delayed half of a send that is otherwise identical to an
 * immediate one — same SendStaffReply, same signature, same footer. A
 * delayed mail is not a second sender.
 *
 * Carries the time it was scheduled *for* so that rescheduling needs no
 * bookkeeping: the job compares what it was dispatched against with what the
 * row now says, and a stale job — one whose message was rescheduled or
 * cancelled — simply does nothing. That is why cancelling leaves no orphan
 * to chase down, and why two dispatches for one message cannot both send.
 */
class SendScheduledMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly string $scheduledFor,
    ) {}

    public function handle(SendStaffReply $send): void
    {
        $message = $this->message->fresh();

        // Deleted (cancelled), already sent, or rescheduled since this job
        // was dispatched.
        if ($message === null
            || $message->scheduled_for === null
            || $message->delivery_state !== null
            || $message->scheduled_for->toIso8601String() !== $this->scheduledFor
        ) {
            return;
        }

        $send($message);
    }
}
