<?php

namespace App\Gallery\Console\Commands;

use App\Gallery\Actions\RemindGalleryExpiry;
use Illuminate\Console\Command;

class RemindGalleryExpiryCommand extends Command
{
    protected $signature = 'galleries:remind-expiry';

    protected $description = 'Mails the primary contact of a gallery that expires within two weeks, once per gallery.';

    public function handle(RemindGalleryExpiry $remind): int
    {
        $this->info(($remind)().' Kunden erinnert.');

        return self::SUCCESS;
    }
}
