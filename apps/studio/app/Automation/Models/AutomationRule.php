<?php

namespace App\Automation\Models;

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationTriggerType;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Automation\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * E-08: a data-driven rule with a domain-event OR a date-offset trigger —
 * never both, guarded below — and one fixed action: send a notification via
 * App\Notifications\NotificationService (App\Automation\Notifications\
 * AutomationReminder carries notification_subject/notification_body).
 *
 * A date-offset rule's anchor decides what App\Automation\Actions\
 * SweepDateOffsetAutomationRules fires against, and it is the whole reason
 * this story exists: `per_shoot` fires once per matching Shoot on a
 * multi-shoot project ("seven days before the shoot" means a different date
 * per shoot), while `primary_shoot` fires once against the Project of
 * whichever Shoot is flagged is_primary ("after the wedding" means the one
 * day, not every shoot the project happens to have). See
 * App\Automation\Models\AutomationRun's own docblock for the anchor entity
 * each produces.
 */
class AutomationRule extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'name',
        'trigger_type',
        'event_class',
        'anchor',
        'offset_days',
        'direction',
        'notification_subject',
        'notification_body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => AutomationTriggerType::class,
            'anchor' => AutomationAnchor::class,
            'direction' => AutomationOffsetDirection::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The invariant the migration's own docblock names: an event rule
     * carries event_class and nothing date-offset-shaped; a date-offset rule
     * carries anchor/offset_days/direction and no event_class. Checked here
     * rather than a DB CHECK constraint, since every write to this table
     * goes through this model.
     */
    protected static function booted(): void
    {
        static::saving(function (self $rule) {
            if ($rule->trigger_type === AutomationTriggerType::Event) {
                if ($rule->event_class === null) {
                    throw new InvalidArgumentException('An event-triggered AutomationRule needs an event_class.');
                }

                $rule->anchor = null;
                $rule->offset_days = null;
                $rule->direction = null;

                return;
            }

            if ($rule->anchor === null || $rule->offset_days === null || $rule->direction === null) {
                throw new InvalidArgumentException('A date-offset AutomationRule needs anchor, offset_days and direction.');
            }

            $rule->event_class = null;
        });
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeDateOffset(Builder $query): void
    {
        $query->where('trigger_type', AutomationTriggerType::DateOffset);
    }

    public function scopeForEvent(Builder $query, string $eventClass): void
    {
        $query->where('trigger_type', AutomationTriggerType::Event)->where('event_class', $eventClass);
    }

    protected static function newFactory(): Factory
    {
        return AutomationRuleFactory::new();
    }
}
