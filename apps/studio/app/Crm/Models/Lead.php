<?php

namespace App\Crm\Models;

use App\Catalog\Models\Package;
use App\Concerns\BelongsToTenant;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\StagePipeline;
use App\Crm\Support\CrmVocabulary;
use App\Models\User;
use BackedEnum;
use Database\Factories\Crm\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pursuit. One per project, created at inquiry.
 *
 * Thin on purpose. Vision §3.1 names the cost — "show me this client" hops
 * ClientCase → Lead to read the funnel stage — and takes it, because collapsing
 * Lead into ClientCase would leave the project table holding lead columns for
 * every booked record.
 *
 * No public id of its own (#520): an inquiry is addressed by its case.
 */
class Lead extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'case_id',
        'package_id',
        'source',
        'source_other',
        'stage',
        'stage_entered_at',
        'stalled_reminders_sent',
        'stalled_reminded_at',
        'inquiry_text',
        'captured_via',
        'captured_via_other',
        'captured_by_id',
        'captured_at',
        'referred_by_contact_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'captured_via' => CaptureChannel::class,
            'stage_entered_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'stalled_reminded_at' => 'immutable_datetime',
        ];
    }

    /**
     * The stage row behind the stored key.
     *
     * An accessor rather than a cast class: a custom cast keeps whatever
     * object was assigned to it (Model::$classCastCache), so a factory or
     * seeder writing one of the seed enums would read that enum back until the
     * model was refreshed. Normalising on the way in and resolving on the way
     * out means one type comes back whatever was written.
     */
    protected function stage(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(CrmVocabulary::class)->byKey(StagePipeline::Lead, $value),
            set: fn (Stage|BackedEnum|string|null $value) => match (true) {
                $value === null => null,
                $value instanceof Stage => $value->key,
                $value instanceof BackedEnum => (string) $value->value,
                default => $value,
            },
            // Or an assigned enum would be handed back unchanged: Eloquent keeps
            // whatever object was set, and the seed enums are objects.
        )->withoutObjectCaching();
    }

    public function sourceLabel(): ?string
    {
        return $this->source_other === null
            ? $this->source?->label()
            : $this->source->label().' — '.$this->source_other;
    }

    public function capturedViaLabel(): ?string
    {
        return $this->captured_via_other === null
            ? $this->captured_via?->label()
            : $this->captured_via->label().' — '.$this->captured_via_other;
    }

    /** Same rule as ClientCase::booted() — a detail exists only under "Sonstige". */
    protected static function booted(): void
    {
        static::saving(function (self $lead) {
            if ($lead->source !== LeadSource::Other) {
                $lead->source_other = null;
            }

            if ($lead->captured_via !== CaptureChannel::Other) {
                $lead->captured_via_other = null;
            }
        });
    }

    /** The name stays: LeadReadData carries it into the OpenAPI contract. */
    public function isLost(): bool
    {
        return $this->case->isClosed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_id');
    }

    /**
     * The contact who sent this client. Null is the ordinary case — see the
     * migration for why it is not required.
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'referred_by_contact_id');
    }

    /**
     * Still in play. Every read that asks "what is the studio pursuing" —
     * the board, the availability scan, the dashboard count — goes through
     * here rather than repeating the null check.
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereHas('case', fn ($case) => $case->whereNull('cases.closed_at'));
    }

    /**
     * Not booked yet, so still an inquiry. cases.booked_at is the seam — a booked
     * record is a project and lives at /projekte, which reads the same fact.
     * The funnel's own reads (the API snapshot, Auswertungen) keep counting
     * booked leads: a funnel without its end is not a funnel.
     */
    public function scopeUnbooked(Builder $query): void
    {
        $query->whereDoesntHave('case', fn ($project) => $project->booked());
    }

    protected static function newFactory(): Factory
    {
        return LeadFactory::new();
    }
}
