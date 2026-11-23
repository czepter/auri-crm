<?php

namespace App\Privacy\Actions;

use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormAnswer;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Gallery\Models\GalleryView;
use App\Models\AuditLog;
use App\Notifications\Models\InAppNotification;
use App\Notifications\Models\NotificationPreference;
use App\Portal\Models\ClientAccess;

/**
 * X-04's read half: everything the studio holds about one Contact, walked
 * out from the same fan-out App\Privacy\Actions\EraseContactDsgvo purges —
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
                'social_profiles' => $contact->social_profiles,
                'street' => $contact->street,
                'postcode' => $contact->postcode,
                'city' => $contact->city,
                'portal_overrides' => $contact->portal_overrides,
                'portal_access_revoked_at' => $contact->portal_access_revoked_at?->toIso8601String(),
                'dsgvo_erased_at' => $contact->dsgvo_erased_at?->toIso8601String(),
                'created_at' => $contact->created_at?->toIso8601String(),
            ],
            'projects' => $contact->cases()
                ->withPivot('role')
                ->get()
                ->map(fn (ClientCase $project) => $this->case($project, $contact))
                ->values()
                ->all(),
            'threads' => $contact->threads()
                ->whereNull('case_id')
                ->get()
                ->map(fn (Thread $thread) => $this->thread($thread, $contact))
                ->all(),
            'form_answers' => FormAnswer::query()
                ->where('contact_id', $contact->id)
                ->with('formInstanceField')
                ->get()
                ->map(fn (FormAnswer $answer) => [
                    'label' => $answer->formInstanceField?->label,
                    'value' => $answer->value,
                    'created_at' => $answer->created_at?->toIso8601String(),
                ])->all(),
            'asset_comments' => AssetComment::query()
                ->where('contact_id', $contact->id)
                ->get()
                ->map(fn (AssetComment $comment) => [
                    'id' => $comment->public_id,
                    'asset_key' => $comment->asset_key,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])->all(),
            'asset_favorites' => AssetFavorite::query()
                ->where('contact_id', $contact->id)
                ->get()
                ->map(fn (AssetFavorite $favorite) => [
                    'id' => $favorite->public_id,
                    'asset_key' => $favorite->asset_key,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ])->all(),
            'gallery_views' => GalleryView::query()
                ->where('contact_id', $contact->id)
                ->with('gallery')
                ->get()
                ->map(fn (GalleryView $view) => [
                    'gallery' => $view->gallery?->public_id,
                    'viewed_on' => $view->viewed_on?->toDateString(),
                ])->all(),
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
            // Was das Studio dieser Person vorgeschlagen hat, samt Satz.
            'service_suggestions' => ServiceSuggestion::query()
                ->where('contact_id', $contact->id)
                ->with('package')
                ->get()
                ->map(fn (ServiceSuggestion $suggestion) => [
                    'id' => $suggestion->public_id,
                    'package' => $suggestion->package?->name,
                    'note' => $suggestion->note,
                    'created_at' => $suggestion->created_at?->toIso8601String(),
                ])->all(),
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
            // opaque public_id (see AcceptQuote) — these rows
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

    private function case(ClientCase $project, Contact $contact): array
    {
        return [
            'id' => $project->public_id,
            'name' => $project->name,
            'type' => $project->type?->key,
            'role' => $project->pivot->role,
            'lead' => $project->lead ? [
                'source' => $project->lead->source?->value,
                'stage' => $project->lead->stage?->key,
                'inquiry_text' => $project->lead->inquiry_text,
                'stage_entered_at' => $project->lead->stage_entered_at?->toIso8601String(),
            ] : null,
            'booked_at' => $project->booked_at?->toIso8601String(),
            'booked_via' => $project->booked_via?->value,
            'payment_plan' => $project->paymentPlan ? [
                'total' => $project->paymentPlan->total,
                'reference' => $project->paymentPlan->reference,
                'installments' => $project->paymentPlan->installments->map(fn ($i) => [
                    'amount' => $i->amount,
                    'due_on' => $i->due_on?->toIso8601String(),
                    'status' => $i->status?->value,
                    'paid_on' => $i->paid_on?->toIso8601String(),
                ])->all(),
            ] : null,
            'shoots' => $project->shoots->map(fn ($shoot) => [
                'id' => $shoot->public_id,
                'kind' => $shoot->kind,
                'label' => $shoot->label,
                'date' => $shoot->date?->toIso8601String(),
                'venue' => $shoot->venue(),
                'status' => $shoot->status?->key,
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
                'fields' => $instance->fields->where('type', '!=', FormFieldType::Section)->map(fn ($field) => [
                    'label' => $field->label,
                    'answer' => $field->currentAnswer?->value,
                ])->all(),
            ])->all(),
            'threads' => $project->threads->map(fn (Thread $thread) => $this->thread($thread, $contact))->all(),
        ];
    }

    private function thread(Thread $thread, Contact $contact): array
    {
        return [
            'id' => $thread->public_id,
            'subject' => $thread->subject,
            'messages' => $thread->messages
                // Only this contact's own words and the studio's replies —
                // never another contact's message (see class docblock).
                ->filter(fn (Message $m) => $m->contact_id === $contact->id || $m->author_id !== null)
                ->map(fn (Message $m) => [
                    'from' => $m->contact_id !== null ? 'contact' : 'staff',
                    'body' => $m->body,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }
}
