<?php

namespace App\Engagement\Actions;

use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Thread;
use App\Engagement\Support\InboundRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * E-12: one inbound delivery becomes a message on a thread, a row in the
 * triage tray, or nothing at all.
 *
 * ### Nothing at all comes first
 *
 * Before a single field is written, the recipient must resolve to a studio
 * account (InboundRecipient). Mail addressed to something this system does
 * not issue is **rejected**: no row, no sender address, no subject, no body.
 *
 * That is a lawfulness question rather than a tidiness one. A stranger
 * writing to an address that belongs to nobody has no relationship with any
 * studio here — no consent, no contract, no legitimate interest — so keeping
 * what they wrote "in case someone wants to look" is processing with no
 * basis for it. It is also the only thing standing between a spam run and
 * one studio's tray filling up with another studio's mail, since
 * `inbound_messages` would otherwise take its tenant from whichever account
 * the poller happened to be running as.
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
    public function __construct(private readonly PostMessage $post) {}

    /**
     * Null means rejected: the recipient belongs to no studio account, so
     * nothing about the sender was stored. See the class note.
     *
     * @param  array{message_id?: ?string, from: string, to: string, subject?: ?string, body: string, spf?: ?string, dkim?: ?string, dmarc?: ?string, spam_score?: ?int}  $delivery
     */
    public function __invoke(array $delivery): ?InboundMessage
    {
        $recipient = InboundRecipient::resolve($delivery['to']);

        if ($recipient === null) {
            return $this->reject($delivery);
        }

        $existing = filled($delivery['message_id'] ?? null)
            ? InboundMessage::query()->where('message_id', $delivery['message_id'])->first()
            : null;

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($delivery, $recipient) {
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

            // The tenant the *address* resolved to, never the ambient one.
            // BelongsToTenant would otherwise file a delivery under whichever
            // studio the poller happened to be running as — which is the
            // whole reason the recipient is resolved before anything is
            // written.
            $row->tenant_id = $recipient->tenantId;

            $this->route($row, $recipient);
            $row->save();

            return $row;
        });
    }

    /**
     * Dropped without a row, and without the sender's address, subject or
     * body touching this database.
     *
     * What is kept is the address it was sent *to* — ours, not anybody's
     * personal data — and the reason, so "why is mail to this address
     * bouncing" stays answerable. Deliberately a log line rather than an
     * `audit_logs` row: that table is tenant-scoped, and this delivery
     * belongs to no tenant, which is the entire finding.
     *
     * The real place to refuse this is the MTA, at RCPT TO, so the mail is
     * never accepted in the first place. That needs a recipient-validation
     * callback on the mail host; until one exists this is the second-best
     * moment — the first one this application controls.
     */
    private function reject(array $delivery): null
    {
        Log::warning('Inbound mail rejected: recipient belongs to no studio account.', [
            'to' => $delivery['to'],
        ]);

        return null;
    }

    private function route(InboundMessage $row, InboundRecipient $recipient): void
    {
        if (mb_strtolower((string) $row->dmarc) === 'fail') {
            $row->state = InboundMessageState::Quarantined;
            $row->reason = 'dmarc_fail';

            return;
        }

        $threadId = $recipient->threadPublicId;

        if ($threadId === null) {
            // A catch-all delivery: it resolved to this account, which is
            // what made it lawful to keep, but it names no conversation. The
            // studio decides which one it belongs to.
            $row->reason = 'catch_all';

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
