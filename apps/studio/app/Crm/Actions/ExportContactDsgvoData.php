<?php

namespace App\Crm\Actions;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Message;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Models\AuditLog;
use App\Notifications\Models\InAppNotification;
use App\Notifications\Models\NotificationPreference;
use App\Portal\Models\ClientAccess;

/**
 * X-04's read half: everything the studio holds about one Contact, walked
 * out from the same fan-out App\Crm\Actions\EraseContactDsgvo purges —
 * every table one belongs to the other, kept in sync on purpose. public_id
 * only, everywhere: no internal id ever leaves this array, the same rule
 * every Inertia prop in this app already follows.
 *
 * A shared project (a partner, a planner, a payer) exposes only this
 * contact's own messages and their own place in a thread — never another
 * contact's public_id or words. Their data is theirs to ask for separately
 * (GDPR Art. 15(4): a subject-access request must not adversely affect the
 * rights of others).
 *
 * gallery_prerelease_signups is matched by address, not by a foreign key —
 * see GalleryPrereleaseSignup's own docblock for why it carries no
 * contact_id at all. A row here is always pending (a notified or
 * pre-release-stopped one deletes itself, same source), so this can only
 * ever show "still waiting", never a history of past ones.
 */
class ExportContactDsgvoData
{
    public function __invoke(Contact $contact): array
    {
        return [
            'contact' => [
                'id' => $contact->public_id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'portal_access_revoked_at' => $contact->portal_access_revoked_at?->toIso8601String(),
                'dsgvo_erased_at' => $contact->dsgvo_erased_at?->toIso8601String(),
                'created_at' => $contact->created_at?->toIso8601String(),
            ],
            'projects' => $contact->projects()
                ->withPivot('role')
                ->get()
                ->map(fn (Project $project) => $this->project($project, $contact))
                ->values()
                ->all(),
            'client_accesses' => ClientAccess::query()
                ->where('contact_id', $contact->id)
                ->get()
                ->map(fn (ClientAccess $access) => [
                    'id' => $access->public_id,
                    'requested_ip' => $access->requested_ip,
                    'expires_at' => $access->expires_at?->toIso8601String(),
                    'consumed_at' => $access->consumed_at?->toIso8601String(),
                    'created_at' => $access->created_at?->toIso8601String(),
                ])->all(),
            // $contact->email is null once dsgvo_erased_at is set (this
            // export stays reachable for an already-erased contact — see
            // ContactDsgvoController::export(), which unlike erase() itself
            // carries no such guard), and there is no address left to match.
            'gallery_prerelease_signups' => $contact->email === null ? [] : GalleryPrereleaseSignup::query()
                ->where('email', GalleryPrereleaseSignup::normalizeEmail($contact->email))
                ->with('gallery')
                ->get()
                ->map(fn (GalleryPrereleaseSignup $signup) => [
                    'gallery' => $signup->gallery?->public_id,
                    'gallery_title' => $signup->gallery?->title,
                    'created_at' => $signup->created_at?->toIso8601String(),
                ])->all(),
            'api_tokens' => $contact->tokens()->get()->map(fn ($token) => [
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->all(),
            'notifications' => InAppNotification::query()
                ->where('notifiable_type', Contact::class)
                ->where('notifiable_id', $contact->id)
                ->get()
                ->map(fn (InAppNotification $n) => [
                    'type' => $n->type,
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                ])->all(),
            'notification_preferences' => NotificationPreference::query()
                ->where('notifiable_type', Contact::class)
                ->where('notifiable_id', $contact->id)
                ->get()
                ->map(fn (NotificationPreference $p) => [
                    'notification_class' => $p->notification_class,
                    'channel' => $p->channel,
                    'enabled' => $p->enabled,
                ])->all(),
            // AuditLogger::record only ever writes a Contact's identity as its
            // opaque public_id (see SignContract, AcceptQuote) — these rows
            // are the append-only trail F-06 requires and are never purged by
            // erasure, so exporting them as-is leaks nothing erasure would
            // not already have made unresolvable.
            'audit_log' => AuditLog::query()
                ->where('subject', $contact->public_id)
                ->orWhere('context->contact', $contact->public_id)
                ->orderBy('created_at')
                ->get()
                ->map(fn (AuditLog $log) => [
                    'event' => $log->event,
                    'context' => $log->context,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    private function project(Project $project, Contact $contact): array
    {
        return [
            'id' => $project->public_id,
            'name' => $project->name,
            'type' => $project->type?->value,
            'role' => $project->pivot->role,
            'lead' => $project->lead ? [
                'source' => $project->lead->source?->value,
                'stage' => $project->lead->stage?->key,
                'inquiry_text' => $project->lead->inquiry_text,
                'stage_entered_at' => $project->lead->stage_entered_at?->toIso8601String(),
            ] : null,
            'job' => $project->job ? [
                'booked_at' => $project->job->booked_at?->toIso8601String(),
                'booked_via' => $project->job->booked_via?->value,
                'payment_plan' => $project->job->paymentPlan ? [
                    'total' => $project->job->paymentPlan->total,
                    'reference' => $project->job->paymentPlan->reference,
                    'installments' => $project->job->paymentPlan->installments->map(fn ($i) => [
                        'amount' => $i->amount,
                        'due_on' => $i->due_on?->toIso8601String(),
                        'status' => $i->status?->value,
                        'paid_on' => $i->paid_on?->toIso8601String(),
                    ])->all(),
                ] : null,
            ] : null,
            'shoots' => $project->shoots->map(fn ($shoot) => [
                'id' => $shoot->public_id,
                'kind' => $shoot->kind,
                'label' => $shoot->label,
                'date' => $shoot->date?->toIso8601String(),
                'venue' => $shoot->venue(),
                'status' => $shoot->status?->value,
                'timeline' => $shoot->timeline->map(fn ($t) => [
                    'time' => $t->time, 'title' => $t->title, 'location' => $t->location, 'note' => $t->note,
                ])->all(),
                'shot_list' => $shoot->shotListItems->map(fn ($item) => [
                    'label' => $item->label, 'checked_off_at' => $item->checked_off_at?->toIso8601String(),
                ])->all(),
            ])->all(),
            'tasks' => $project->tasks->map(fn ($task) => [
                'title' => $task->title, 'due_on' => $task->due_on?->toIso8601String(), 'completed_at' => $task->completed_at?->toIso8601String(),
            ])->all(),
            'notes' => $project->notes->map(fn ($note) => [
                'body' => $note->body, 'created_at' => $note->created_at?->toIso8601String(),
            ])->all(),
            'contracts' => $project->contracts->map(function ($contract) use ($contact) {
                $signature = $contract->signature;

                return [
                    'id' => $contract->public_id,
                    'status' => $contract->status?->value,
                    'rendered_at' => $contract->rendered_at?->toIso8601String(),
                    // Only this contact's own signature — a co-signer's typed
                    // name and IP are their own personal data.
                    'signed_by_this_contact' => $signature !== null && $signature->contact_id === $contact->id ? [
                        'typed_name' => $signature->typed_name,
                        'consented_at' => $signature->consented_at?->toIso8601String(),
                        'ip_address' => $signature->ip_address,
                    ] : null,
                ];
            })->all(),
            'quotes' => $project->quotes->map(fn ($quote) => [
                'id' => $quote->public_id,
                'status' => $quote->status?->value,
                'total' => $quote->total,
                'sent_at' => $quote->sent_at?->toIso8601String(),
                'accepted_at' => $quote->accepted_by_contact_id === $contact->id ? $quote->accepted_at?->toIso8601String() : null,
                'items' => $quote->items->map(fn ($item) => [
                    'title' => $item->title, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                ])->all(),
            ])->all(),
            'galleries' => $project->galleries->map(fn ($gallery) => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'is_external' => $gallery->is_external,
                'versions' => $gallery->versions->map(fn ($version) => [
                    'version' => $version->version,
                    'published_at' => $version->published_at?->toIso8601String(),
                    'asset_count' => is_array($version->assets) ? count($version->assets) : 0,
                ])->all(),
            ])->all(),
            'form_instances' => $project->formInstances->map(fn ($instance) => [
                'name' => $instance->name,
                'fields' => $instance->fields->map(fn ($field) => [
                    'label' => $field->label,
                    'answer' => $field->currentAnswer?->value,
                ])->all(),
            ])->all(),
            'threads' => $project->threads->map(function ($thread) use ($contact) {
                return [
                    'id' => $thread->public_id,
                    'subject' => $thread->subject,
                    'messages' => $thread->messages
                        // Only this contact's own words and the studio's
                        // replies — never another contact's message on a
                        // shared project (see class docblock).
                        ->filter(fn (Message $m) => $m->contact_id === $contact->id || $m->author_id !== null)
                        ->map(fn (Message $m) => [
                            'from' => $m->contact_id !== null ? 'contact' : 'staff',
                            'body' => $m->body,
                            'created_at' => $m->created_at?->toIso8601String(),
                        ])->values()->all(),
                ];
            })->all(),
        ];
    }
}
