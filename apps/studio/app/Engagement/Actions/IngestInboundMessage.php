<?php

namespace App\Engagement\Actions;

use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Thread;
use App\Engagement\Support\InboundAddress;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Facades\DB;

/**
 * E-12: one inbound delivery becomes either a message on a thread or a row
 * in the triage tray. Never nothing.
 *
 * ### Threading needs all three
 *
 * 1. a `t.` local part,
 * 2. whose HMAC verifies under this tenant's current `inbound_secret`,
 * 3. **and** a From: matching a contact already on that thread's
 *    ThreadParticipant rows.
 *
 * The third is the one worth defending. An address is trivially forged, so
 * attaching on From: alone would let a stranger who learned a client's
 * address post into that client's conversation with the studio — and the
 * studio would read it as the client. Anything failing any of the three goes
 * to triage, where a human attaches it deliberately.
 *
 * ### What is never silently dropped
 *
 * A DMARC failure is quarantined and visible, not discarded: "the client
 * says they replied and we never saw it" has to be answerable, and an
 * ingest that throws mail away cannot answer it.
 *
 * Idempotent on the sender's Message-ID, so a provider replaying a delivery
 * — which they all do — creates one message rather than two.
 */
class IngestInboundMessage
{
    public function __construct(
        private readonly PostMessage $post,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /**
     * @param  array{message_id?: ?string, from: string, to: string, subject?: ?string, body: string, spf?: ?string, dkim?: ?string, dmarc?: ?string, spam_score?: ?int}  $delivery
     */
    public function __invoke(array $delivery): InboundMessage
    {
        $existing = filled($delivery['message_id'] ?? null)
            ? InboundMessage::query()->where('message_id', $delivery['message_id'])->first()
            : null;

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($delivery) {
            $row = new InboundMessage([
                'message_id' => $delivery['message_id'] ?? null,
                'from_address' => mb_strtolower($delivery['from']),
                'to_address' => mb_strtolower($delivery['to']),
                'subject' => $delivery['subject'] ?? null,
                'body' => $delivery['body'],
                'spf' => $delivery['spf'] ?? null,
                'dkim' => $delivery['dkim'] ?? null,
                'dmarc' => $delivery['dmarc'] ?? null,
                'spam_score' => $delivery['spam_score'] ?? null,
                'state' => InboundMessageState::Triage,
            ]);

            $this->route($row);
            $row->save();

            return $row;
        });
    }

    private function route(InboundMessage $row): void
    {
        if (mb_strtolower((string) $row->dmarc) === 'fail') {
            $row->state = InboundMessageState::Quarantined;
            $row->reason = 'dmarc_fail';

            return;
        }

        $secret = $this->settings->inboundSecret();

        if ($secret === null) {
            $row->reason = 'no_inbound_secret';

            return;
        }

        $threadId = InboundAddress::threadIdFrom($row->to_address, $secret);

        if ($threadId === null) {
            // A catch-all delivery, a forged local part, or an address minted
            // before the last rotation. All three are the studio's to look at.
            $row->reason = 'unrecognised_address';

            return;
        }

        $thread = Thread::query()->where('public_id', $threadId)->first();

        if ($thread === null) {
            $row->reason = 'unknown_thread';

            return;
        }

        $row->thread_id = $thread->id;

        $participant = $thread->participants()
            ->whereNotNull('contact_id')
            ->with('contact')
            ->get()
            ->first(fn ($participant) => mb_strtolower((string) $participant->contact?->email) === $row->from_address);

        if ($participant === null) {
            // The address verified, so this really is that thread's reply
            // address — but the sender is not on the conversation. Deliberate
            // triage rather than an attach: see the class note.
            $row->reason = 'sender_not_participant';

            return;
        }

        $message = ($this->post)($thread, $participant->contact, $row->body);

        $row->contact_id = $participant->contact->id;
        $row->message_id_ref = $message->id;
        $row->state = InboundMessageState::Threaded;
        $row->reason = null;
    }
}
