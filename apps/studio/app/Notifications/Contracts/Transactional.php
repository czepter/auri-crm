<?php

namespace App\Notifications\Contracts;

/**
 * T-11's "stated list of which mails are transactional": every Notification
 * class implementing this interface is exempt from a preference suppression
 * and from quiet hours, both enforced inside NotificationService::send()
 * before a transport is ever called. "Transactional" here means the same
 * thing App\Notifications\StaffInvited and App\Portal\Notifications\
 * ClientAccessLink already are — the mail IS the thing the recipient is
 * waiting on right now, not a status update about something else — so
 * grep-ing `implements Transactional` across app/ is the stated list the
 * issue asked for, rather than a name repeated in a config file that can
 * drift from what the classes actually do.
 */
interface Transactional {}
