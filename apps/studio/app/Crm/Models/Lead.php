<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Models\User;
use Database\Factories\Crm\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pursuit. One per project, created at inquiry.
 *
 * Thin on purpose. Vision §3.1 names the cost — "show me this client" hops
 * Project → Lead to read the funnel stage — and takes it, because collapsing
 * Lead into Job would leave a table named Job holding mostly unbooked
 * inquiries.
 */
class Lead extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id',
        'source',
        'stage',
        'stage_entered_at',
        'lost_at',
        'lost_reason',
        'inquiry_text',
        'captured_via',
        'captured_by_id',
        'referred_by_contact_id',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'source' => LeadSource::class,
            'captured_via' => CaptureChannel::class,
            'stage_entered_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
        ];
    }

    public function isLost(): bool
    {
        return $this->lost_at !== null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
        $query->whereNull('lost_at');
    }

    protected static function newFactory(): Factory
    {
        return LeadFactory::new();
    }
}
