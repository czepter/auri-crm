<?php

namespace App\Notifications\Contracts;

/**
 * Goes out even while the studio has switched client mail off
 * (mail_clients_enabled), because the recipient asked for it a moment ago
 * and nothing opens without it: login codes, access links, address
 * confirmations, the download link. WithholdClientMail reads this; the
 * classification test makes every new notification pick a side.
 */
interface IgnoresClientMailSwitch {}
