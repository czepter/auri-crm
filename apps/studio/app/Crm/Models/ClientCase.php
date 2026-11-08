<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Contracts\Models\Contract;
use App\Crm\Enums\BookedVia;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Support\CrmVocabulary;
use App\Engagement\Models\Thread;
use App\Enums\Capability;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Models\Quote;
use BackedEnum;
use Database\Factories\Crm\ClientCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
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
class ClientCase extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $table = 'cases';

    protected $fillable = ['name', 'type', 'type_other', 'portal_visible'];

    /**
     * Mirrors the column default in memory. Without it a freshly created
     * ClientCase reports portal_visible as null until it is reloaded, and a
     * visibility flag that reads null where it means false is the kind of
     * thing a guard clause gets wrong exactly once.
     */
    protected $attributes = ['portal_visible' => false];

    protected function casts(): array
    {
        return [
            'portal_visible' => 'boolean',
            // C-25: keyed by ProjectField::$key. Written only through
            // WriteProjectFieldValues, which is why it is not fillable.
            'field_values' => 'array',
            // Written only through CompleteCase.
            'completed_at' => 'datetime',
            // Written only through BookCase and UnbookCase.
            'booked_at' => 'immutable_datetime',
            'booked_via' => BookedVia::class,
            // Written only through AdvanceLeadStage::markLost() and reopen().
            'closed_at' => 'immutable_datetime',
            'close_outcome' => LeadOutcome::class,
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** A booked case the client lost is a cancelled booking, not a lost inquiry. */
    public function closeLabel(): ?string
    {
        return $this->isBooked() && $this->close_outcome === LeadOutcome::Lost
            ? 'Storniert'
            : $this->close_outcome?->label();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /** A booked project is an Auftrag; everything else is still an Anfrage. */
    public function isBooked(): bool
    {
        return $this->booked_at !== null;
    }

    public function scopeBooked(Builder $query): void
    {
        $query->whereNotNull('cases.booked_at');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }

    /** Absent until CreatePaymentPlan writes it (M-03) — one plan per project, at most. */
    public function paymentPlan(): HasOne
    {
        return $this->hasOne(PaymentPlan::class);
    }

    /**
     * The type row behind the stored key. An accessor for the reason
     * Lead::stage() is one.
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(CrmVocabulary::class)->type($value),
            set: fn (ProjectType|BackedEnum|string|null $value) => match (true) {
                $value === null => null,
                $value instanceof ProjectType => $value->key,
                $value instanceof BackedEnum => (string) $value->value,
                default => $value,
            },
        )->withoutObjectCaching();
    }

    /** The occasion as a screen shows it — "Sonstige" carries its detail. */
    public function typeLabel(): string
    {
        return $this->type_other === null
            ? $this->type->name
            : $this->type->name.' — '.$this->type_other;
    }

    /**
     * A detail belongs to "Sonstige" alone. Cleared here rather than at each
     * writer, so a type change from the case screen cannot leave the old
     * free text standing under Hochzeit.
     */
    protected static function booted(): void
    {
        static::saving(function (self $project) {
            if (! $project->type?->isCatchAll()) {
                $project->type_other = null;
            }
        });
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'case_contacts')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * attach() writes through the query builder, which bypasses the model
     * events BelongsToTenant and HasPublicId hook into. This is the one place
     * in the context where the seam columns are filled by hand.
     */
    /**
     * Die Rolle ist eine Zeichenkette in der Datenbank und darf auch eine
     * sein: „Trauredner" ist eine Rolle, die niemand in eine Aufzählung
     * schreiben wird. CaseContactRole bleibt die Liste der Vorschläge und
     * der einen Rolle, an der ein Index hängt.
     */
    public function attachContact(Contact $contact, CaseContactRole|string $role): void
    {
        $this->contacts()->attach($contact, [
            'role' => $role instanceof CaseContactRole ? $role->value : $role,
            'tenant_id' => config('tenancy.tenant_id'),
            'public_id' => (string) new Cuid2,
        ]);
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
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

    /**
     * The one a screen shows: the most recent render, whatever its status —
     * but only once it has gone out. Seit Verträge Fassungen haben, liegt vor
     * der versendeten ein Entwurf, und das Portal liest diese Relation.
     */
    public function latestContract(): HasOne
    {
        return $this->hasOne(Contract::class)->whereNotNull('sent_at')->latestOfMany();
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

    /**
     * Each of these is a record of something agreed with a client, and a
     * payment plan hangs off the booking. Past that line the project is no longer
     * something to take back; it gets closed instead.
     *
     * @return Collection<int, string>
     */
    public function deletionBlockers(): Collection
    {
        return collect([
            'booking' => $this->isBooked(),
            'quote' => $this->quotes()->exists(),
            'contract' => $this->contracts()->exists(),
            'gallery' => $this->galleries()->exists(),
        ])->filter()->keys();
    }

    /** Every questionnaire personalised for this project (E-02), newest first. */
    public function formInstances(): HasMany
    {
        return $this->hasMany(FormInstance::class)->latest();
    }

    /**
     * E-06's conversations, newest first. The Kommunikation tab and the
     * portal's own thread screen both read latestThread() below rather than
     * this whole list — there is no screen yet that shows more than one.
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class)->latest();
    }

    /** The one a screen shows: the most recently started conversation. */
    public function latestThread(): HasOne
    {
        return $this->hasOne(Thread::class)->latestOfMany();
    }

    /** The one the studio works on: the newest version not yet replaced, drafts included. */
    public function currentQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('status', '!=', 'superseded'));
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

    /**
     * P-03 (#222): may the client still change the date and the place
     * themselves?
     *
     * Until something is reserved, the answer is theirs — they wrote those
     * lines into an inquiry form and a typo in them is their typo to fix.
     * The moment it is booked, or any shoot has moved past `requested`, the
     * same two fields are contract content and the way to change them is to
     * ask (P-07). Contact details are not covered by this and never are:
     * see ContactController on the portal surface.
     */
    public function clientMayEditDetails(): bool
    {
        return ! $this->isBooked()
            && ! $this->shoots()->where('status', '!=', app(CrmVocabulary::class)->anchored(StagePipeline::Project, StageAnchor::Entry)?->key)->exists();
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
        if ($user->can(Capability::ViewAllCases->value)) {
            return;
        }

        $query->whereExists(
            CrewAssignment::query()
                ->join('shoots', 'shoots.id', '=', 'crew_assignments.shoot_id')
                ->whereColumn('shoots.case_id', 'cases.id')
                ->where('crew_assignments.user_id', $user->id)
                ->select(DB::raw(1))
        );
    }

    /**
     * Primary contact first, then the rest, falling back to the project name
     * while nobody is attached. Reads the loaded relation: every caller shows
     * a list and has eager-loaded it.
     */
    public function contactNames(): string
    {
        return $this->contacts
            ->sortBy(fn (Contact $contact) => $contact->pivot->role === CaseContactRole::Primary->value ? 0 : 1)
            ->pluck('name')
            ->join(' & ') ?: $this->name;
    }

    public function primaryContact(): ?Contact
    {
        return $this->contacts()
            ->wherePivot('role', CaseContactRole::Primary->value)
            ->first();
    }

    // `case` alone: the default would derive client_case_id from the class name.
    public function getForeignKey(): string
    {
        return 'case_id';
    }

    protected static function newFactory(): Factory
    {
        return ClientCaseFactory::new();
    }
}
