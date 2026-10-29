<?php

// apps/studio/tests/Feature/Notifications/TwoBrandMailTest.php

use App\Models\StudioSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

class TwoBrandProductDemo extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting('Hallo Nina,')
            ->line('Testzeile.');
    }
}

it('renders a product mail with the fixed product brand, not the studio one', function () {
    StudioSettings::query()->update(['display_name' => 'Studio Nordlicht']);
    Cache::store('redis')->flush();

    $html = (string) (new TwoBrandProductDemo)->toMail(User::factory()->create())->render();

    expect($html)->toContain('Auri CRM')->and($html)->not->toContain('Studio Nordlicht');
});

it("renders the studio's own brand when a mail does not pass one explicitly", function () {
    StudioSettings::query()->update(['display_name' => 'Studio Nordlicht']);
    Cache::store('redis')->flush();

    $html = (string) (new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->greeting('Hallo Nina,')->line('Testzeile.');
        }
    })->toMail(User::factory()->create())->render();

    expect($html)->toContain('Studio Nordlicht');
});
