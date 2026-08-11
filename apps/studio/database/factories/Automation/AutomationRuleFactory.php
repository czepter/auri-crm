<?php

namespace Database\Factories\Automation;

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Reminder — '.fake()->words(2, true),
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::PerShoot,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => 'Erinnerung',
            'notification_body' => 'Nur eine kurze Erinnerung.',
            'is_active' => true,
        ];
    }

    public function dateOffset(AutomationAnchor $anchor, int $offsetDays, AutomationOffsetDirection $direction): static
    {
        return $this->state(fn () => [
            'trigger_type' => AutomationTriggerType::DateOffset,
            'event_class' => null,
            'anchor' => $anchor,
            'offset_days' => $offsetDays,
            'direction' => $direction,
        ]);
    }

    public function event(string $eventClass): static
    {
        return $this->state(fn () => [
            'trigger_type' => AutomationTriggerType::Event,
            'event_class' => $eventClass,
            'anchor' => null,
            'offset_days' => null,
            'direction' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
