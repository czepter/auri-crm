<?php

namespace App\Engagement\Console\Commands;

use App\Engagement\Actions\IngestInboundMessage;
use App\Engagement\Support\InboundMimeMessage;
use Illuminate\Console\Command;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * E-12's default inbound adapter: poll the catch-all mailbox this app's own
 * hosting owns.
 *
 * The mailbox is **ours**, not the studio's — `auri-crm.de`, not whatever
 * the photographer signed up for in 2014 — so the property the whole story
 * rests on is untouched: a studio hands over no credential to be inboxed.
 * The webhook adapter beside this one exists for a deployment that would
 * rather let a provider do the receiving; both call the same
 * IngestInboundMessage, so the HMAC check, the three-condition threading
 * rule, triage and idempotency are shared rather than reimplemented.
 *
 * Polling rather than IMAP IDLE. A minute of latency on a client's reply is
 * nothing, and a daemon somebody has to supervise, restart and monitor is
 * considerably more than nothing.
 *
 * Marks a message read only after ingest returns. A crash mid-ingest leaves
 * it unread and it is picked up next minute — and picking it up twice is
 * already harmless, because ingest is idempotent on the sender's Message-ID.
 */
class PollInboundMailbox extends Command
{
    protected $signature = 'mail:poll-inbound {--limit=50 : How many unread messages to take in one pass}';

    protected $description = 'Fetch unread mail from the inbound catch-all mailbox and route it (E-12)';

    public function handle(IngestInboundMessage $ingest): int
    {
        $config = config('mail.inbound.imap');

        if (blank($config['host']) || blank($config['username'])) {
            // Not an error: a deployment using the webhook adapter, or one
            // with no inbound mail at all, runs this scheduler entry too.
            $this->components->info('No inbound mailbox configured; nothing to poll.');

            return self::SUCCESS;
        }

        try {
            $client = (new ClientManager)->make([
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'],
                'validate_cert' => $config['validate_cert'],
                'username' => $config['username'],
                'password' => $config['password'],
                'protocol' => 'imap',
            ]);

            $client->connect();
        } catch (Throwable $e) {
            // Reported rather than thrown: a mailbox that is briefly
            // unreachable is a transient condition the next run fixes, and a
            // failing scheduled command every minute is noise that trains
            // people to ignore the scheduler.
            report($e);
            $this->components->error('Inbound mailbox unreachable: '.$e->getMessage());

            return self::FAILURE;
        }

        $messages = $client->getFolder($config['folder'])
            ->query()
            ->unseen()
            ->limit((int) $this->option('limit'))
            ->get();

        $handled = 0;
        $rejected = 0;

        foreach ($messages as $message) {
            try {
                $row = $ingest(InboundMimeMessage::fromRaw($this->raw($message)));

                if ($row === null) {
                    // Rejected: the recipient belongs to no studio account,
                    // so the ingest kept nothing — and neither does the
                    // mailbox. Leaving it sitting there read would be the
                    // same personal data in a different store.
                    $message->delete();
                    $rejected++;

                    continue;
                }

                $message->setFlag('Seen');
                $handled++;
            } catch (Throwable $e) {
                // Left unread on purpose, so the next pass retries it. A
                // message this app cannot parse is exactly the case that must
                // not vanish quietly — the same rule the triage tray exists
                // for, one layer earlier.
                report($e);
                $this->components->warn('Could not ingest a message: '.$e->getMessage());
            }
        }

        $this->components->info("Ingested {$handled} message(s), rejected {$rejected}.");

        return self::SUCCESS;
    }

    /** Headers and body as they arrived — InboundMimeMessage needs both, in order. */
    private function raw(Message $message): string
    {
        return $message->getHeader()->raw."\r\n\r\n".$message->getRawBody();
    }
}
