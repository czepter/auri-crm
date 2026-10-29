<?php

namespace App\Engagement\Support;

use Webklex\PHPIMAP\Message;

/**
 * One raw RFC 822 message, flattened into the shape IngestInboundMessage
 * takes.
 *
 * This is the whole difference between the two inbound adapters. A provider
 * webhook hands over an already-parsed delivery with its own authentication
 * verdicts as fields; a mailbox hands over bytes. Everything downstream —
 * the HMAC check, the three-condition threading rule, triage, idempotency —
 * is identical either way, which is why the poller is an adapter rather than
 * a second ingest.
 *
 * ### Why the verdicts come from the topmost header only
 *
 * `Authentication-Results` is written by the receiving MTA, and a sender is
 * perfectly free to put a forged one in the message they send us. The one
 * our own MTA wrote is prepended, so the **first** occurrence is the only
 * one that means anything; every one below it arrived with the mail and is
 * the sender's own claim about themselves.
 *
 * If `mail.inbound.authserv_id` is configured, the first header must also
 * carry that identity — belt and braces for a relay chain that prepends its
 * own. Unset, the topmost is trusted on position alone.
 */
class InboundMimeMessage
{
    /**
     * @return array{message_id: ?string, from: string, to: string, subject: ?string, body: string, spf: ?string, dkim: ?string, dmarc: ?string, spam_score: ?int}
     */
    public static function fromRaw(string $raw): array
    {
        $message = Message::fromString($raw);
        $verdicts = self::verdicts($raw);

        return [
            'message_id' => self::header($raw, 'Message-ID'),
            'from' => (string) ($message->getFrom()[0]->mail ?? ''),
            // The envelope recipient is what carries the `t.<thread>.<hmac>`
            // local part, and a client's mail client may well have put the
            // studio's public address in To: while the delivery reached a
            // thread address via Delivered-To. Both are checked, in the order
            // that gets threading right more often.
            'to' => self::recipient($raw, $message),
            'subject' => $message->getSubject()->first() ?: null,
            'body' => self::body($message),
            'spf' => $verdicts['spf'] ?? null,
            'dkim' => $verdicts['dkim'] ?? null,
            'dmarc' => $verdicts['dmarc'] ?? null,
            'spam_score' => self::spamScore($raw),
        ];
    }

    /**
     * Text over HTML. The composer that will show this is a plain-text one
     * (E-06's `messages.body` is a text column), and a client's HTML part is
     * the same words wrapped in markup their mail client generated.
     *
     * Deliberately does not strip the quoted original underneath a reply:
     * every heuristic for that is wrong on some client, and a message with
     * too much context is recoverable where one with a sentence cut off is
     * not.
     */
    private static function body(Message $message): string
    {
        $text = trim((string) $message->getTextBody());

        if ($text !== '') {
            return $text;
        }

        return trim(PlainTextBody::fromHtml((string) $message->getHTMLBody()));
    }

    /**
     * `Delivered-To` first: it names the address the delivery was actually
     * made to, which for a `t.` reply address is the whole point. `To:` is
     * the sender's own idea of where it went and survives forwarding badly.
     */
    private static function recipient(string $raw, Message $message): string
    {
        return self::header($raw, 'Delivered-To')
            ?? self::header($raw, 'X-Original-To')
            ?? (string) ($message->getTo()[0]->mail ?? '');
    }

    /** @return array<string, string> */
    private static function verdicts(string $raw): array
    {
        $line = self::header($raw, 'Authentication-Results');

        if ($line === null) {
            return [];
        }

        $expected = config('mail.inbound.authserv_id');

        if (filled($expected) && ! str_starts_with(mb_strtolower(trim($line)), mb_strtolower((string) $expected))) {
            // The topmost header was not written by the MTA we trust, so it
            // is somebody's claim rather than our own check.
            return [];
        }

        $verdicts = [];

        // Per resinfo (`;`-separated), and only the leading `method=result`:
        // a property value such as `smtp.mailfrom=dmarc=pass@x` is the
        // sender's text, not a verdict.
        foreach (explode(';', $line) as $resinfo) {
            if (preg_match('/^\s*(spf|dkim|dmarc)\s*=\s*(\w+)/i', $resinfo, $match) === 1) {
                $verdicts[mb_strtolower($match[1])] ??= mb_strtolower($match[2]);
            }
        }

        return $verdicts;
    }

    private static function spamScore(string $raw): ?int
    {
        $line = self::header($raw, 'X-Spam-Score') ?? self::header($raw, 'X-Spam-Level');

        return $line === null ? null : (int) round((float) $line);
    }

    /**
     * The **first** occurrence of a header, unfolded. Read off the raw block
     * rather than through the parser's attribute bag: this class cares about
     * header order (see the class note), and an attribute map keyed by name
     * has already thrown that away.
     */
    private static function header(string $raw, string $name): ?string
    {
        $headerBlock = preg_split("/\r?\n\r?\n/", $raw, 2)[0] ?? $raw;

        // Continuation lines start with whitespace — folded by the sender at
        // whatever width their client chose, and meaningless to split on.
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $headerBlock) ?? $headerBlock;

        foreach (preg_split("/\r?\n/", $unfolded) ?: [] as $line) {
            if (preg_match('/^'.preg_quote($name, '/').':\s*(.*)$/i', $line, $match) === 1) {
                return trim($match[1]) !== '' ? trim($match[1]) : null;
            }
        }

        return null;
    }
}
