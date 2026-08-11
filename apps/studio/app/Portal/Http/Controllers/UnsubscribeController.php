<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use App\Notifications\Support\NotificationPreferences;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-11: the unsubscribe every non-transactional client mail's footer links
 * to (see QuoteSent). Public and unauthenticated by necessity — same
 * reasoning as the magic link and the calendar feed (routes/portal.php) —
 * with the contact's own public_id as the whole credential, exactly as
 * unguessable as any other public_id and never displayed back past its
 * first character.
 *
 * Writes a single '*' row (NotificationPreferences::set($contact, '*',
 * 'mail', false)) rather than one row per notification class — see that
 * class's own docblock — so a class that does not exist yet is covered by
 * the same click.
 */
class UnsubscribeController extends Controller
{
    public function show(Contact $contact, NotificationPreferences $preferences): Response
    {
        return Inertia::render('Unsubscribe', [
            'contact' => $contact->public_id,
            'unsubscribed' => $preferences->unsubscribed($contact),
        ]);
    }

    public function store(Contact $contact, NotificationPreferences $preferences): RedirectResponse
    {
        $preferences->set($contact, '*', 'mail', false);

        AuditLogger::record(
            event: 'notification_preference.unsubscribed',
            context: ['channel' => 'mail'],
            subject: $contact->public_id,
        );

        return redirect("/abmelden/{$contact->public_id}");
    }
}
