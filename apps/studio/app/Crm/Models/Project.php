<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Enums\Capability;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Quotes\Models\Quote;
use Database\Factories\Crm\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

/**
 * The relationship, from inquiry to archive. Owns the contacts, the
 * conversation and the shoots.
 *
 * Carries no pipeline status on purpose: a project whose engagement shoot is
 * delivered while the wedding day is unshot has no single state, and inventing
 * one produces a status that is wrong for most of a wedding's eighteen-month
 * life (vision §3.1). Board position is derived.
 */
class Project extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'type', 'portal_visible'];

    /**
     * Mirrors the column default in memory. Without it a freshly created
     * Project reports portal_visible as null until it is reloaded, and a
     * visibility flag that reads null where it means false is the kind of
     * thing a guard clause gets wrong exactly once.
     */
    protected $attributes = ['portal_visible' => false];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'portal_visible' => 'boolean',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'project_contacts')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * attach() writes through the query builder, which bypasses the model
     * events BelongsToTenant and HasPublicId hook into. This is the one place
     * in the context where the seam columns are filled by hand.
     */
    public function attachContact(Contact $contact, ProjectContactRole $role): void
    {
        $this->contacts()->attach($contact, [
            'role' => $role->value,
            'tenant_id' => config('tenancy.tenant_id'),
            'public_id' => (string) new Cuid2,
        ]);
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    /** Absent until the project is booked — its existence is the commitment. */
    public function job(): HasOne
    {
        return $this->hasOne(Job::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InternalNote::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Every render this project has ever had, newest first. M-01. */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->latest();
    }

    /** The one a screen shows: the most recent render, whatever its status. */
    public function latestContract(): HasOne
    {
        return $this->hasOne(Contract::class)->latestOfMany();
    }

    public function shoots(): HasMany
    {
        return $this->hasMany(Shoot::class)->orderBy('sort_order');
    }

    /** Every delivery gallery this project has, backfilled or linked alike (G-05, X-02). */
    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest();
    }

    /** Every questionnaire personalised for this project (E-02), newest first. */
    public function formInstances(): HasMany
    {
        return $this->hasMany(FormInstance::class)->latest();
    }

    /**
     * The one the portal shows: the most recently sent quote, whatever its
     * status now reads. Ordered on sent_at rather than created_at — a
     * revision (App\Quotes\Actions\ReviseQuote) exists as a draft row the
     * moment it is created, and this must keep pointing at its still-sent
     * predecessor until that revision is itself sent, the same instant
     * Quote::booted()'s guard starts refusing further writes to it.
     */
    public function latestQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->whereNotNull('sent_at')->latestOfMany('sent_at');
    }

    /** The headline occasion — the wedding day, not the engagement shoot. */
    public function primaryShoot(): HasOne
    {
        return $this->hasOne(Shoot::class)->where('is_primary', true);
    }

    /**
     * Is this person on the crew of any of the project's shoots? The one fact
     * a crew role's whole visibility hangs off.
     *
     * Cancelled shoots count: someone who was crewed on a day that got called
     * off still has a reason to see why, and hiding the project the moment it
     * is cancelled is how staff conclude the system is lying to them.
     */
    public function isCrewedBy(User $user): bool
    {
        return CrewAssignment::query()
            ->whereIn('shoot_id', $this->shoots()->select('id'))
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Projects this person may see, for the reads that list many at once. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->allows(Capability::ViewAllProjects)) {
            return;
        }

        $query->whereExists(
            CrewAssignment::query()
                ->join('shoots', 'shoots.id', '=', 'crew_assignments.shoot_id')
                ->whereColumn('shoots.project_id', 'projects.id')
                ->where('crew_assignments.user_id', $user->id)
                ->select(DB::raw(1))
        );
    }

    public function primaryContact(): ?Contact
    {
        return $this->contacts()
            ->wherePivot('role', ProjectContactRole::Primary->value)
            ->first();
    }

    protected static function newFactory(): Factory
    {
        return ProjectFactory::new();
    }
}
