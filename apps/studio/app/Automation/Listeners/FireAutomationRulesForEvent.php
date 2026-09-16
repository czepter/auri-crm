<?php

namespace App\Automation\Listeners;

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Models\AutomationRule;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Support\SilentWrite;
use Carbon\CarbonImmutable;
use ReflectionObject;
use ReflectionProperty;

/**
 * E-08's domain-event half. Wired once as a wildcard listener
 * (`Event::listen('*', ...)` in AppServiceProvider::boot()) rather than once
 * per event class: a rule naming `App\Crm\Events\ProjectBooked` in its own
 * event_class column IS the subscription, so a new rule for an event class
 * this app already dispatches needs no code change here.
 *
 * The anchor is whichever public Project or Shoot property the firing event
 * carries — every domain event in this codebase already exposes its subject
 * that way (see ProjectBooked, InstallmentPaid), and Project
 * and Shoot are the only two entities E-08 ever anchors a rule to. An event
 * naming neither is simply not something an AutomationRule can watch yet.
 */
class FireAutomationRulesForEvent
{
    public function __construct(private readonly FireAutomationRule $fire) {}

    /** @param array<int, object> $payload */
    public function handle(string $eventName, array $payload): void
    {
        // Wildcard means every event dispatched app-wide, including
        // framework noise (queue, cache, console) no rule could ever name —
        // skip the query for anything that isn't one of this app's own
        // classes before touching the database.
        if (! str_starts_with($eventName, 'App\\')) {
            return;
        }

        // Before FireAutomationRule, not inside it: that action claims an
        // AutomationRun row before it sends, and a run marked Sent whose
        // notification the guard above dropped is a history that lies.
        if (SilentWrite::active()) {
            return;
        }

        $event = $payload[0] ?? null;

        if (! is_object($event)) {
            return;
        }

        $rules = AutomationRule::query()->active()->forEvent($eventName)->get();

        if ($rules->isEmpty()) {
            return;
        }

        $anchor = $this->resolveAnchor($event);

        if ($anchor === null) {
            return;
        }

        $triggerDate = CarbonImmutable::now('UTC')->toDateString();

        foreach ($rules as $rule) {
            ($this->fire)($rule, $anchor, $triggerDate);
        }
    }

    private function resolveAnchor(object $event): Project|Shoot|null
    {
        foreach ((new ReflectionObject($event))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($event);

            if ($value instanceof Project || $value instanceof Shoot) {
                return $value;
            }
        }

        return null;
    }
}
