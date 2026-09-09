<?php

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;

// docker-compose.yml sets MAIL_MAILER=smtp on the php service so local mail
// reaches Mailpit. A real environment variable also lands in $_SERVER, which
// Laravel's env repository reads before $_ENV — so an <env> line in
// phpunit.xml, force or not, cannot outrank it. Without the matching <server>
// line the suite dialled mailpit:1025 for real.
it('sends through the array transport', function () {
    expect(Mail::mailer()->getSymfonyTransport())->toBeInstanceOf(ArrayTransport::class);
});
