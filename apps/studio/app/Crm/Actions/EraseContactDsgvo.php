<?php

namespace App\Crm\Actions;

use App\Automation\Models\AutomationRun;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Models\Contact;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Engagement\Models\ThreadParticipant;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Media\Models\Asset;
use App\Models\User;
use App\Notifications\Models\InAppNotification;
use App\Notifications\Models\NotificationPreference;
use App\Portal\Models\ClientAccess;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * X-04's write half: erases one Contact and everything reachable from them.
 *
 * The Contact row itself is never dropped. quotes.accepted_by_contact_id
 * restrictOnDelete a contact who has accepted something and the database
 * enforces it, so erasure means scrubbing this row's identifying columns in
 * place, never removing it. Every table this contact's projects touch is
 * purged outright, storage objects included.
 *
 * A project shared with another contact (a partner, a payer, a planner) is
 * never touched beyond this contact's own fingerprints on it — the other
 * party's lawful basis for the studio holding that data does not end when
 * this one asks to be forgotten.
 */
class EraseContactDsgvo
{
    public function __invoke(Contact $contact, User $actor): void
    {
        DB::transaction(function () use ($contact, $actor) {
            foreach ($contact->projects()->get() as $project) {
                $sharedWithSomeoneElse = $project->contacts()->whereKeyNot($contact->id)->exists();

                if ($sharedWithSomeoneElse) {
                    $this->detachFromSharedProject($project, $contact);

                    continue;
                }

                $this->purgeSoleContactProject($project);
            }

            ClientAccess::where('contact_id', $contact->id)->delete();

            // Der Satz, den jemand über diese Person geschrieben hat, ist
            // personenbezogen wie jede Notiz zu ihr.
            ServiceSuggestion::where('contact_id', $contact->id)->delete();
            // By address, not contact_id — GalleryPrereleaseSignup carries
            // no foreign key to a Contact at all (same reasoning as
            // GalleryEmailVerification), so this is the one place erasure
            // can still find it. Global rather than scoped to the projects
            // above: purgeSoleContactProject's Gallery::delete() already
            // cascades this away for a sole-contact project, but a shared
            // project's gallery is never dropped (detachFromSharedProject
            // only strips this contact's own fingerprints), and a contact
            // may have signed up for a gallery's pre-release that belongs
            // to no project of theirs at all.
            if ($contact->email !== null) {
                GalleryPrereleaseSignup::where('email', GalleryPrereleaseSignup::normalizeEmail($contact->email))->delete();
            }
            $contact->tokens()->delete();
            InAppNotification::where('notifiable_type', Contact::class)->where('notifiable_id', $contact->id)->delete();
            NotificationPreference::where('notifiable_type', Contact::class)->where('notifiable_id', $contact->id)->delete();

            $contact->forceFill([
                'name' => '[gelöscht]',
                'email' => null,
                'phone' => null,
                'portal_access_revoked_at' => $contact->portal_access_revoked_at ?? now(),
                'dsgvo_erased_at' => now(),
            ])->save();

            // The contact's identity survives here only as an already-opaque
            // public_id (AuditLogger's own convention, e.g. AcceptQuote) —
            // no name, email or phone ever reaches an audit row, so nothing
            // above needs redacting for this one to be safe to keep forever.
            AuditLogger::record(
                event: 'contact.dsgvo_erased',
                actor: $actor,
                subject: $contact->public_id,
            );
        });
    }

    /** Strips only this contact's own fingerprints; the project and its shared content stay for whoever else is on it. */
    private function detachFromSharedProject(Project $project, Contact $contact): void
    {
        $threadIds = $project->threads()->pluck('id');

        Message::whereIn('thread_id', $threadIds)->where('contact_id', $contact->id)->delete();
        ThreadParticipant::whereIn('thread_id', $threadIds)->where('contact_id', $contact->id)->delete();
        $project->contacts()->detach($contact->id);
    }

    /**
     * Purges every erasable table under this project — the whole fan-out:
     * quotes/items, galleries/assets/derivatives/zip builds/download events/
     * favorites/comments (and their storage objects), threads/messages,
     * form instances/fields/answers, tasks, notes, shoots and everything
     * under one (crew, timeline, shot list), the lead, the job and its
     * payment plan, and any automation run anchored to the project or a
     * shoot of it, then the project row itself.
     */
    private function purgeSoleContactProject(Project $project): void
    {
        $this->purgeStorageObjects($project);

        Quote::where('project_id', $project->id)->delete();
        Thread::where('project_id', $project->id)->delete();
        Gallery::where('project_id', $project->id)->delete();
        $project->formInstances()->delete();
        Task::where('project_id', $project->id)->delete();
        InternalNote::where('project_id', $project->id)->delete();

        $shootIds = $project->shoots()->pluck('id');
        Shoot::where('project_id', $project->id)->delete();
        Lead::where('project_id', $project->id)->delete();
        Job::where('project_id', $project->id)->delete();

        AutomationRun::where(fn ($q) => $q->where('anchorable_type', Project::class)->where('anchorable_id', $project->id))
            ->orWhere(fn ($q) => $q->where('anchorable_type', Shoot::class)->whereIn('anchorable_id', $shootIds))
            ->delete();

        $project->delete();
    }

    private function purgeStorageObjects(Project $project): void
    {
        $s3 = Storage::disk('s3');

        foreach ($project->galleries as $gallery) {
            foreach (Asset::where('gallery_id', $gallery->id)->with('derivatives')->get() as $asset) {
                $s3->delete($asset->key);
                $s3->delete($asset->derivatives->pluck('key')->all());
            }

            foreach ($gallery->multipartUploads as $upload) {
                $s3->delete($upload->key);
            }

            foreach ($gallery->versions as $version) {
                foreach ($version->zipBuilds as $zipBuild) {
                    $s3->delete($zipBuild->parts->pluck('key')->all());
                }
            }
        }
    }
}
