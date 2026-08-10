<?php

namespace App\Integration\Console\Commands;

use App\Integration\Enums\WebhookDeliveryStatus;
use App\Integration\Jobs\DeliverWebhookDelivery;
use App\Integration\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * I-04's replay path: resends a past delivery's exact stored payload
 * (WebhookDelivery::body()) to its endpoint without the original domain
 * event firing again. A new WebhookDelivery row rather than resetting the
 * old one — the original stays exactly as it was decided (Delivered or
 * Failed), and the replay gets its own attempt/backoff history, visible
 * side by side with the delivery it replayed.
 */
class RedeliverWebhookCommand extends Command
{
    protected $signature = 'webhook:redeliver {delivery : The public_id of the WebhookDelivery to resend}';

    protected $description = 'Resends a WebhookDelivery\'s exact original payload to its endpoint as a new delivery attempt.';

    public function handle(): int
    {
        $original = WebhookDelivery::findByPublicId($this->argument('delivery'));

        if ($original === null) {
            $this->error("No WebhookDelivery found for \"{$this->argument('delivery')}\".");

            return self::FAILURE;
        }

        $replay = WebhookDelivery::create([
            'webhook_endpoint_id' => $original->webhook_endpoint_id,
            'event_type' => $original->event_type,
            'payload' => $original->payload,
            'status' => WebhookDeliveryStatus::Pending,
        ]);

        DeliverWebhookDelivery::dispatch($replay);

        $this->info("Replaying {$original->public_id} as {$replay->public_id}.");

        return self::SUCCESS;
    }
}
