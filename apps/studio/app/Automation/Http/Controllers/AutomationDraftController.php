<?php

namespace App\Automation\Http\Controllers;

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationService;
use App\Notifications\Support\MailPreview;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * #438: a client mail an automation drafted, sent or discarded by the studio.
 * Listed in the Posteingang, gated like it.
 */
class AutomationDraftController extends Controller
{
    public function compose(AutomationRun $automationRun, StudioSettingsResolver $settings): Response
    {
        $this->authorizeDraft($automationRun);

        $contacts = $automationRun->contacts();

        return Inertia::render('Automation/SendDraft', [
            'draftId' => $automationRun->public_id,
            'subject' => $automationRun->subject,
            'body' => $automationRun->body,
            'recipients' => $contacts->map(fn (Contact $contact) => ['name' => $contact->name, 'email' => $contact->email])->values()->all(),
            'mailHtml' => MailPreview::html(
                new AutomationReminder($automationRun->subject, MailPreview::MESSAGE, $settings),
                $contacts->first() ?? (object) [],
            ),
        ]);
    }

    public function send(Request $request, AutomationRun $automationRun, NotificationService $notifications, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeDraft($automationRun);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:20000'],
        ]);

        // Claimed before sending: a second click finds nothing left to send.
        $claimed = AutomationRun::query()
            ->whereKey($automationRun->getKey())
            ->where('status', AutomationRunStatus::AwaitingApproval)
            ->update(['status' => AutomationRunStatus::Sent, 'subject' => $validated['subject'], 'body' => $validated['message']]);

        abort_if($claimed === 0, 404);

        try {
            foreach ($automationRun->contacts() as $contact) {
                $notifications->send($contact, new AutomationReminder($validated['subject'], $validated['message'], $settings));
            }
        } catch (Throwable $e) {
            $automationRun->update(['status' => AutomationRunStatus::Failed, 'error' => $e->getMessage()]);

            throw $e;
        }

        return redirect('/posteingang');
    }

    public function discard(AutomationRun $automationRun): RedirectResponse
    {
        $this->authorizeDraft($automationRun);

        $automationRun->update(['status' => AutomationRunStatus::Skipped]);

        return back();
    }

    private function authorizeDraft(AutomationRun $run): void
    {
        abort_unless(auth('staff')->user()?->can(Capability::ManageMessages->value) ?? false, 403);
        abort_unless($run->status === AutomationRunStatus::AwaitingApproval, 404);

        $project = FireAutomationRule::projectOf($run->anchorable);
        if ($project !== null) {
            $this->authorize('manageMessages', $project);
        }
    }
}
