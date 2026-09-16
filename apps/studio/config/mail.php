<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    // Unset, the product mails (AppServiceProvider's mail::layout composer) carry no footer.
    'support_address' => env('MAIL_SUPPORT_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Inbound Mail (E-11, E-12)
    |--------------------------------------------------------------------------
    |
    | The domain this app's own hosting owns for *receiving* mail — never the
    | studio's mailbox. E-12's per-thread reply addresses and account
    | catch-all are minted under it, and E-11 uses it for the domain half of
    | every outgoing Message-ID.
    |
    | Unset until the MX records exist, which is what keeps E-12's ingest
    | inert on a deployment that has not provisioned one.
    |
    */

    'inbound' => [
        'domain' => env('MAIL_INBOUND_DOMAIN'),
        'max_bytes' => (int) env('MAIL_INBOUND_MAX_BYTES', 26_214_400),

        // The identity our own MTA writes into Authentication-Results. Only
        // the topmost header carrying it is trusted for SPF/DKIM/DMARC —
        // everything below it arrived with the mail and is the sender's own
        // claim about themselves. See InboundMimeMessage.
        'authserv_id' => env('MAIL_INBOUND_AUTHSERV_ID'),

        // Adapter one: poll our own catch-all mailbox over IMAP. The default,
        // because the mailbox belongs to this app's hosting — the studio
        // still hands over nothing.
        'imap' => [
            'host' => env('MAIL_INBOUND_IMAP_HOST'),
            'port' => (int) env('MAIL_INBOUND_IMAP_PORT', 993),
            'encryption' => env('MAIL_INBOUND_IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => (bool) env('MAIL_INBOUND_IMAP_VALIDATE_CERT', true),
            'username' => env('MAIL_INBOUND_IMAP_USERNAME'),
            'password' => env('MAIL_INBOUND_IMAP_PASSWORD'),
            'folder' => env('MAIL_INBOUND_IMAP_FOLDER', 'INBOX'),
        ],

        // Adapter two: a provider POSTs each delivery to /api/mail/eingang,
        // signed with this. Unset on an IMAP deployment, and the endpoint
        // then refuses everything rather than accepting anything.
        'signing_secret' => env('MAIL_INBOUND_SIGNING_SECRET'),
    ],

];
