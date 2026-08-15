<?php

namespace App\Engagement\Support;

use App\Engagement\Models\Message;

/**
 * The RFC 5322 Message-ID one outgoing message carries.
 *
 * Derived from the row's own `public_id` rather than assigned by the mailer,
 * so the row knows it before the send instead of after — E-12 threads inbound
 * replies on this column, and a value that only exists once a transport
 * happens to report it is not something a reply can be matched against.
 *
 * The domain half comes from the configured inbound domain when there is one
 * and the app's own host otherwise, because a Message-ID's domain part is
 * expected to be one the sender controls.
 */
class MessageIdentifier
{
    public static function for(Message $message): string
    {
        return '<msg-'.$message->public_id.'@'.self::domain().'>';
    }

    public static function domain(): string
    {
        $configured = config('mail.inbound.domain');

        if (filled($configured)) {
            return (string) $configured;
        }

        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }
}
