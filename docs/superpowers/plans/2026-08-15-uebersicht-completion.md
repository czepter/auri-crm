# The Übersicht Completes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the three remaining action columns, the four-tile KPI row and the *unbeantwortet* header link to the Übersicht, so the built screen matches `mockups/admin/01-uebersicht.html` in everything its data can honestly support.

**Architecture:** One reader action per domain — `ReadInquiryColumn` (Quotes), `ReadPaymentColumn` (Payments), `ReadAfterDeliveryColumn` (Gallery) — each viewer-scoped, derived, writing nothing, each returning `Collection<DomainRowData>`, mirroring the existing `App\Crm\Actions\ReadProductionBoard`. `DashboardController` composes them into props and gains no queries of its own. One `DomainColumn.vue` renders any column from that shape.

**Tech Stack:** Laravel 13, Inertia + Vue 3 (`<script setup lang="ts">`), `spatie/laravel-data` with `#[TypeScript]`, Pest, PostgreSQL. All PHP runs in Docker: prefix every artisan/test command with `docker exec studio-dev-server`.

**Spec:** [`docs/superpowers/specs/2026-08-15-uebersicht-completion-design.md`](../specs/2026-08-15-uebersicht-completion-design.md) — read it first; this plan argues from it.

## Global Constraints

Copied from the Definition of Done on issue #26, which applies to every story in this repo.

- Tests written before implementation. The audience matrix grows with every new route group.
- **No integer identifier** in any Inertia prop, JSON body, URL, email or filename. Use `public_id`. `UebersichtTest` already asserts this screen-wide — that test must keep passing.
- Every new table carries `tenant_id` and `public_id` from its first migration (`$table->tenantId()`, `$table->publicId()`). Columns added to existing tables inherit the parent's.
- Conventional Commits. CI green on every job.
- Timestamps UTC. `Shoot.date` stays a `date` column.
- Any screen a task touches is held next to `mockups/admin/01-uebersicht.html` at the same viewport, with a screenshot on the PR.
- **German copy is terse and native.** Labels and hints go in `resources/js/locales/de.json`. No explanatory sentences where a noun does the job.
- No new CSS. `.doms`, `.dom`, `.dh`, `.dr`, `.who`, `.what`, `.abtn`, `.abtn.hot`, `.blkfoot`, `.kpis`, `.kpi` already exist in `resources/css/tokens.css` and `admin-extra.css`, byte-identical to `mockups/_shared/`. A column that seems to need a new class is a wrong column.

## Thresholds

The spec left three windows unspecified as studio judgement. These are the defaults; they live as named private constants on their reader action so a later settings story has one place to move them from.

| constant | value | meaning |
| --- | --- | --- |
| `ReadInquiryColumn::EXPIRY_WINDOW_DAYS` | `14` | a sent quote whose `valid_until` falls inside this many days |
| `ReadInquiryColumn::UNSIGNED_AFTER_DAYS` | `4` | a rendered contract unsigned this long |
| `ReadPaymentColumn::DUNNING_EXHAUSTED_DAYS` | `3` | `overdue_notified_at` at least this old |
| `ReadAfterDeliveryColumn::EXPIRY_WINDOW_DAYS` | `30` | a gallery whose `expires_at` falls inside this many days |

## File Structure

**Created**

| file | responsibility |
| --- | --- |
| `app/Crm/Data/DomainRowData.php` | the row shape every column returns |
| `app/Crm/Data/RowActionData.php` | one row's action: label, kind, target |
| `app/Quotes/Actions/ReadInquiryColumn.php` | S-08's rows |
| `app/Payments/Actions/ReadPaymentColumn.php` | S-09's rows |
| `app/Gallery/Actions/ReadAfterDeliveryColumn.php` | S-10's rows |
| `resources/js/admin/components/DomainColumn.vue` | renders any column from `DomainRowData[]` |
| `tests/Feature/Quotes/InquiryColumnTest.php` | S-08 reader |
| `tests/Feature/Payments/PaymentColumnTest.php` | S-09 reader |
| `tests/Feature/Gallery/AfterDeliveryColumnTest.php` | S-10 reader |

**Modified**

| file | change |
| --- | --- |
| `app/Automation/Enums/AutomationAnchor.php` | `+ QuoteValidity` |
| `app/Automation/Actions/SweepDateOffsetAutomationRules.php` | the quote branch |
| `config/automation.php` | `+ quote-follow-up` rule |
| `app/Http/Controllers/Staff/DashboardController.php` | composes four readers, four KPI figures, the unanswered count |
| `resources/js/admin/pages/Uebersicht.vue` | four columns, four tiles, the header link |
| `resources/js/locales/de.json` | the new labels |
| `app/Crm/Models/Lead.php`, `app/Crm/Actions/CaptureInquiry.php` | the referrer |
| `app/Gallery/Models/Gallery.php` | `expires_at` |
| `database/seeders/DemoCrmSeeder.php` | one row per column |
| `tests/Feature/Crm/UebersichtTest.php` | the new props |

---

## Task 1: The quote-validity anchor

`SweepDateOffsetAutomationRules` anchors only on shoots today. Without a third anchor there is no quote nudge to count, and decision 2 — a row appears only once the machine has already tried — has nothing to read.

**Files:**
- Modify: `app/Automation/Enums/AutomationAnchor.php`
- Modify: `app/Automation/Actions/SweepDateOffsetAutomationRules.php:37-58`
- Modify: `config/automation.php`
- Test: `tests/Feature/Automation/SweepDateOffsetAutomationRulesTest.php`

**Interfaces:**
- Consumes: `FireAutomationRule::__invoke(AutomationRule $rule, Model $anchor, string $triggerDate): ?AutomationRun` (exists)
- Produces: `AutomationAnchor::QuoteValidity`; a rule keyed `quote-follow-up` firing against a `Project`; `AutomationRun` rows Task 3 counts

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Automation/SweepDateOffsetAutomationRulesTest.php`:

```php
it('fires a quote-validity rule against the quote project', function () {
    // The anchor is the Project, not the Quote: a revision is a new Quote row
    // (Q-06), and a nudge trail that resets when the studio revises an offer
    // would report "never reminded" about a client reminded twice.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(7)->toDateString(),
    ]);

    $rule = AutomationRule::factory()->create([
        'key' => 'quote-follow-up',
        'trigger_type' => AutomationTriggerType::DateOffset,
        'anchor' => AutomationAnchor::QuoteValidity,
        'offset_days' => 7,
        'direction' => AutomationOffsetDirection::Before,
    ]);

    $fired = app(SweepDateOffsetAutomationRules::class)();

    expect($fired)->toBe(1)
        ->and(AutomationRun::query()->where('automation_rule_id', $rule->id)->first())
        ->anchorable_type->toBe($project->getMorphClass())
        ->anchorable_id->toBe($project->id);
});

it('does not fire a quote-validity rule for a quote that is not sent', function () {
    // A draft has not been put in front of anyone; there is nothing to chase.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Draft,
        'valid_until' => now()->addDays(7)->toDateString(),
    ]);

    AutomationRule::factory()->create([
        'key' => 'quote-follow-up',
        'trigger_type' => AutomationTriggerType::DateOffset,
        'anchor' => AutomationAnchor::QuoteValidity,
        'offset_days' => 7,
        'direction' => AutomationOffsetDirection::Before,
    ]);

    expect(app(SweepDateOffsetAutomationRules::class)())->toBe(0);
});
```

Add the imports the file does not already carry: `App\Automation\Enums\AutomationAnchor`, `App\Automation\Models\AutomationRun`, `App\Crm\Models\Project`, `App\Quotes\Enums\QuoteStatus`, `App\Quotes\Models\Quote`.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=SweepDateOffset
```

Expected: FAIL — `"QuoteValidity" is not a valid backing value for enum AutomationAnchor`.

- [ ] **Step 3: Add the enum case**

In `app/Automation/Enums/AutomationAnchor.php`, beside the two existing cases:

```php
    /**
     * The date a sent quote stops being valid. Fires against the quote's
     * Project rather than the Quote: Q-06 makes a revision a new row, and a
     * nudge trail keyed to the row would forget every reminder the moment the
     * studio revises the offer.
     */
    case QuoteValidity = 'quote_validity';
```

- [ ] **Step 4: Add the sweep branch**

`SweepDateOffsetAutomationRules::__invoke` currently builds one `Shoot` query per rule. Split the body so the anchor picks the query. Replace the `foreach` with:

```php
        foreach (AutomationRule::query()->active()->dateOffset()->get() as $rule) {
            $targetDate = $this->targetDateFor($rule, $today);

            foreach ($this->anchorsFor($rule, $targetDate) as $anchor) {
                if (($this->fire)($rule, $anchor, $targetDate->toDateString()) !== null) {
                    $fired++;
                }
            }
        }
```

and add, beside `targetDateFor`:

```php
    /**
     * What this rule fires against today. Two shapes of query, kept apart by
     * the anchor rather than by a flag: shoots carry the date for the first
     * two, a sent quote's validity carries it for the third.
     *
     * @return iterable<int, Model>
     */
    private function anchorsFor(AutomationRule $rule, CarbonImmutable $targetDate): iterable
    {
        if ($rule->anchor === AutomationAnchor::QuoteValidity) {
            return Quote::query()
                ->where('status', QuoteStatus::Sent)
                ->whereDate('valid_until', $targetDate->toDateString())
                ->with('project')
                ->get()
                ->map(fn (Quote $quote) => $quote->project);
        }

        $isPrimary = $rule->anchor === AutomationAnchor::PrimaryShoot;

        return Shoot::query()
            ->uncancelled()
            ->whereDate('date', $targetDate->toDateString())
            ->when($isPrimary, fn ($query) => $query->where('is_primary', true))
            ->with('project')
            ->get()
            ->map(fn (Shoot $shoot) => $isPrimary ? $shoot->project : $shoot);
    }
```

Add `use App\Quotes\Enums\QuoteStatus;`, `use App\Quotes\Models\Quote;` and `use Illuminate\Database\Eloquent\Model;` to the imports.

- [ ] **Step 5: Add the seed rule**

In `config/automation.php`, after the `thank-you-after-wedding` entry:

```php
        [
            'key' => 'quote-follow-up',
            'name' => 'Angebot — Erinnerung vor Ablauf',
            'trigger_type' => AutomationTriggerType::DateOffset,
            'anchor' => AutomationAnchor::QuoteValidity,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'notification_subject' => 'Ihr Angebot läuft bald ab',
            'notification_body' => 'Ihr Angebot gilt noch eine Woche. Melden Sie sich gern, wenn etwas offen ist.',
        ],
```

- [ ] **Step 6: Run the whole automation suite**

```bash
docker exec studio-dev-server php artisan test --filter=Automation
```

Expected: PASS, including the two existing anchors — the refactor in Step 4 must not change what they fire against.

- [ ] **Step 7: Commit**

```bash
git add app/Automation config/automation.php tests/Feature/Automation
git commit -m "feat(automation): quote-validity anchor and the quote follow-up rule"
```

---

## Task 2: The shared row shape

**Files:**
- Create: `app/Crm/Data/DomainRowData.php`
- Create: `app/Crm/Data/RowActionData.php`

**Interfaces:**
- Produces: `DomainRowData` and `RowActionData`, consumed by Tasks 3, 5, 8 and by `DomainColumn.vue` in Task 4

No test of its own — a data object with no behaviour is proved by its first consumer in Task 3, which is written before it is used. This task exists so three later tasks do not each invent the shape.

- [ ] **Step 1: Write `RowActionData`**

```php
<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The one thing a dashboard row offers to do about itself.
 *
 * Two kinds, both anchors: `tel` dials a contact, `link` opens the screen
 * where the decision is taken. There is deliberately no `post` — see the
 * design's Part 0. A third kind arrives with the first row that earns a
 * one-press action, not before.
 */
#[TypeScript]
class RowActionData extends Data
{
    public function __construct(
        public string $label,
        public string $kind,
        public string $target,
    ) {}

    public static function tel(string $label, ?string $phone): ?self
    {
        return $phone === null || trim($phone) === ''
            ? null
            : new self($label, 'tel', 'tel:'.preg_replace('/[^\d+]/', '', $phone));
    }

    public static function link(string $label, string $href): self
    {
        return new self($label, 'link', $href);
    }
}
```

`tel()` returns null on a missing number on purpose: a *Anrufen* button that dials nothing is worse than no button, and the row still states the fact without it.

- [ ] **Step 2: Write `DomainRowData`**

```php
<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of one Übersicht column. Every row in mockup 01 has the same
 * anatomy — who it is about, what is owed, which fragment of that carries
 * emphasis, and the single action that discharges it — so the four columns
 * share one shape rather than four near-identical ones.
 *
 * `emphasis` is the mockup's <i> fragment inside .what: the date or the
 * count that is the reason the row is on the screen at all.
 *
 * `hot` is .abtn.hot — the action is also the overdue mark.
 */
#[TypeScript]
class DomainRowData extends Data
{
    public function __construct(
        public string $projectId,
        public string $who,
        public string $what,
        public ?string $emphasis,
        public ?RowActionData $action,
        public bool $hot = false,
    ) {}
}
```

- [ ] **Step 3: Regenerate the TypeScript types**

```bash
docker exec studio-dev-server php artisan typescript:transform
```

Expected: `resources/js/types/generated.d.ts` gains `App.Crm.Data.DomainRowData` and `App.Crm.Data.RowActionData`.

- [ ] **Step 4: Commit**

```bash
git add app/Crm/Data resources/js/types/generated.d.ts
git commit -m "feat(crm): the shared Übersicht row shape"
```

---

## Task 3: `ReadInquiryColumn` — S-08's rows

**Files:**
- Create: `app/Quotes/Actions/ReadInquiryColumn.php`
- Create: `tests/Feature/Quotes/InquiryColumnTest.php`

**Interfaces:**
- Consumes: `DomainRowData`, `RowActionData` (Task 2); `AutomationAnchor::QuoteValidity` and the `quote-follow-up` rule (Task 1)
- Produces: `ReadInquiryColumn::__invoke(?User $viewer = null): Collection<int, DomainRowData>`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use App\Quotes\Actions\ReadInquiryColumn;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');

    $this->rule = AutomationRule::factory()->create([
        'key' => 'quote-follow-up',
        'trigger_type' => AutomationTriggerType::DateOffset,
        'anchor' => AutomationAnchor::QuoteValidity,
        'offset_days' => 7,
        'direction' => AutomationOffsetDirection::Before,
    ]);
});

function projectWithPhone(string $name, string $phone = '+49 40 1234567'): Project
{
    $project = Project::factory()->create(['name' => $name]);
    $project->attachContact(
        Contact::factory()->create(['phone' => $phone]),
        ProjectContactRole::Primary,
    );

    return $project;
}

it('does not row a silent quote the machine has not chased yet', function () {
    // Decision 2, and the whole design: the Übersicht shows what the machine
    // cannot do. A quote whose reminder has not gone out yet is the machine's
    // job, not a phone call.
    $project = projectWithPhone('Reuter & Sölter');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    expect(app(ReadInquiryColumn::class)())->toBeEmpty();
});

it('rows a silent quote once the reminders are exhausted', function () {
    $project = projectWithPhone('Reuter & Sölter');
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    app(FireAutomationRule::class)($this->rule, $project, now()->subDays(9)->toDateString());
    app(FireAutomationRule::class)($this->rule, $project, now()->subDays(2)->toDateString());

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Reuter & Sölter')
        ->and($rows->first()->what)->toContain('2× erinnert')
        ->and($rows->first()->action->kind)->toBe('tel')
        ->and($rows->first()->action->target)->toBe('tel:+49401234567')
        ->and($rows->first()->hot)->toBeTrue()
        ->and($rows->first()->projectId)->toBe($project->public_id);
});

it('rows an accepted quote with no contract, pointing at the template choice', function () {
    // contracts.store needs a template. The dashboard has no standing to pick
    // one, so the button opens the screen where the studio does.
    $project = projectWithPhone('Duhnkrack & W.');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDays(3),
    ]);

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->what)->toContain('Vertrag fehlt')
        ->and($rows->first()->action->kind)->toBe('link')
        ->and($rows->first()->action->target)->toBe("/auftraege/{$project->public_id}/vertrag");
});

it('does not row an accepted quote once its contract exists', function () {
    $project = projectWithPhone('Duhnkrack & W.');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDays(3),
    ]);
    Contract::factory()->for($project)->create(['status' => ContractStatus::Rendered]);

    expect(app(ReadInquiryColumn::class)())->toHaveCount(1)
        ->and(app(ReadInquiryColumn::class)()->first()->what)->toContain('unsigniert');
});

it('rows a rendered contract left unsigned', function () {
    $project = projectWithPhone('Ahlers & Sobotta');
    Contract::factory()->for($project)->create([
        'status' => ContractStatus::Rendered,
        'created_at' => now()->subDays(6),
    ]);

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->what)->toContain('unsigniert')
        ->and($rows->first()->hot)->toBeFalse();
});

it('drops the action when the contact has no phone number', function () {
    // A button that dials nothing is worse than no button; the row still
    // states the fact.
    $project = projectWithPhone('Ohne Telefon', phone: '');
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);
    app(FireAutomationRule::class)($this->rule, $project, now()->subDay()->toDateString());

    expect(app(ReadInquiryColumn::class)()->first()->action)->toBeNull();
});

it('shows a crew member only their own projects', function () {
    projectWithPhone('Nicht meins');
    Quote::factory()->for(Project::first())->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDay(),
    ]);

    $crew = User::factory()->secondShooter()->withMfa()->create();

    expect(app(ReadInquiryColumn::class)($crew))->toBeEmpty();
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=InquiryColumn
```

Expected: FAIL — `Target class [App\Quotes\Actions\ReadInquiryColumn] does not exist`.

- [ ] **Step 3: Write the reader**

```php
<?php

namespace App\Quotes\Actions;

use App\Automation\Models\AutomationRun;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Models\Project;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Support\Collection;

/**
 * Mockup 01's *Anfragen & Angebote* column: what the studio owes on an offer
 * it has already made.
 *
 * Derived rather than maintained, like ReadProductionBoard — a quote accepted
 * this morning drops off the column on the next read because there is nothing
 * else for it to do.
 *
 * The first row is gated on the automation trail, not on silence alone. The
 * design's decision 2 is the reason: a quote whose reminder has not gone out
 * yet is the machine's work, and putting it here would make this a status
 * report about things already in hand.
 */
class ReadInquiryColumn
{
    private const EXPIRY_WINDOW_DAYS = 14;

    private const UNSIGNED_AFTER_DAYS = 4;

    private const FOLLOW_UP_RULE = 'quote-follow-up';

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        $visible = Project::query()->visibleTo($viewer)->select('projects.id');

        return $this->chasedQuotes($visible)
            ->concat($this->acceptedWithoutContract($visible))
            ->concat($this->unsignedContracts($visible))
            ->values();
    }

    /**
     * A sent quote running out with the reminders spent. The count in the row
     * is the real number of AutomationRuns fired against the project, which is
     * what makes "2× erinnert, keine Reaktion" a fact rather than a phrase.
     *
     * @return Collection<int, DomainRowData>
     */
    private function chasedQuotes($visible): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->whereDate('valid_until', '<=', now()->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString())
            ->whereIn('project_id', $visible)
            ->with('project.contacts')
            ->get()
            ->map(function (Quote $quote) {
                $nudges = $this->nudgeCount($quote->project_id);

                if ($nudges === 0) {
                    return null;
                }

                return new DomainRowData(
                    projectId: $quote->project->public_id,
                    who: $quote->project->name,
                    what: sprintf(
                        'Angebot still · %d× erinnert, keine Reaktion',
                        $nudges,
                    ),
                    emphasis: 'läuft '.$quote->valid_until->format('d.m.').' ab',
                    action: RowActionData::tel('Anrufen', $quote->project->primaryContact()?->phone),
                    hot: true,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * How often the machine has already asked. Kept here rather than as a
     * scope on AutomationRun: this is the only column that counts runs —
     * Zahlungen reads overdue_notified_at and Nach der Lieferung asks a
     * different question entirely — and a shared scope over one call site is
     * an abstraction with nothing to abstract.
     */
    private function nudgeCount(int $projectId): int
    {
        return AutomationRun::query()
            ->whereHas('rule', fn ($query) => $query->where('key', self::FOLLOW_UP_RULE))
            ->where('anchorable_type', (new Project)->getMorphClass())
            ->where('anchorable_id', $projectId)
            ->count();
    }

    /**
     * @return Collection<int, DomainRowData>
     */
    private function acceptedWithoutContract($visible): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->whereIn('project_id', $visible)
            ->whereDoesntHave('project.contracts')
            ->with('project.contacts')
            ->get()
            ->map(fn (Quote $quote) => new DomainRowData(
                projectId: $quote->project->public_id,
                who: $quote->project->name,
                what: 'angenommen '.$quote->accepted_at?->format('d.m.').' —',
                emphasis: 'Vertrag fehlt',
                action: RowActionData::link(
                    'Vertrag senden',
                    "/auftraege/{$quote->project->public_id}/vertrag",
                ),
                hot: true,
            ));
    }

    /**
     * ContractStatus describes the render, not a dispatch — there is no `sent`
     * to test against — so a rendered contract with no signature, aged past
     * the window, is what "unsigniert" means here.
     *
     * @return Collection<int, DomainRowData>
     */
    private function unsignedContracts($visible): Collection
    {
        return Contract::query()
            ->where('status', ContractStatus::Rendered)
            ->whereDoesntHave('signature')
            ->where('created_at', '<=', now()->subDays(self::UNSIGNED_AFTER_DAYS))
            ->whereIn('project_id', $visible)
            ->with('project.contacts')
            ->get()
            ->map(fn (Contract $contract) => new DomainRowData(
                projectId: $contract->project->public_id,
                who: $contract->project->name,
                what: 'Vertrag',
                emphasis: $contract->created_at->diffInDays().' Tage unsigniert',
                action: RowActionData::tel('Anrufen', $contract->project->primaryContact()?->phone),
            ));
    }
}
```

- [ ] **Step 4: Run the test to green**

```bash
docker exec studio-dev-server php artisan test --filter=InquiryColumn
```

Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Quotes/Actions tests/Feature/Quotes
git commit -m "feat(quotes): the Anfragen & Angebote column reader"
```

---

## Task 4: `DomainColumn.vue` and S-08 on the screen

The first column to reach the page brings the component the other two reuse.

**Files:**
- Create: `resources/js/admin/components/DomainColumn.vue`
- Modify: `app/Http/Controllers/Staff/DashboardController.php`
- Modify: `resources/js/admin/pages/Uebersicht.vue`
- Modify: `resources/js/locales/de.json`
- Test: `tests/Feature/Crm/UebersichtTest.php`

**Interfaces:**
- Consumes: `ReadInquiryColumn` (Task 3), `DomainRowData` (Task 2)
- Produces: the `inquiries` Inertia prop; `DomainColumn.vue` with props `title: string`, `count: string`, `rows: App.Crm.Data.DomainRowData[]`, `footer?: string`, `footerHref?: string`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Crm/UebersichtTest.php`:

```php
it('carries the Anfragen & Angebote column, free of integer identifiers', function () {
    $project = Project::factory()->create(['name' => 'Duhnkrack & W.']);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDay(),
    ]);

    $rows = props('/')['inquiries'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['projectId'])->toBe($project->public_id)
        ->and($rows[0]['who'])->toBe('Duhnkrack & W.');
});

it('shows no Anfragen & Angebote column when nothing is owed on an offer', function () {
    // "Absent, not empty" — S-01's rule, and the reason this screen is a
    // worklist rather than a dashboard of zeroes.
    expect(props('/')['inquiries'])->toBeEmpty();
});
```

Add the imports: `App\Crm\Enums\ProjectContactRole`, `App\Crm\Models\Contact`, `App\Quotes\Enums\QuoteStatus`, `App\Quotes\Models\Quote`.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
```

Expected: FAIL — undefined array key `inquiries`.

- [ ] **Step 3: Add the prop**

In `DashboardController::__invoke`, add the reader to the constructor signature and the prop to the render:

```php
    public function __invoke(
        ReadProductionBoard $board,
        ReadInquiryColumn $inquiries,
    ): Response {
        $viewer = auth('staff')->user();

        return Inertia::render('Uebersicht', [
            // ... existing props unchanged ...
            'inquiries' => $inquiries($viewer),
        ]);
    }
```

Add `use App\Quotes\Actions\ReadInquiryColumn;`.

- [ ] **Step 4: Write `DomainColumn.vue`**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{
  title: string
  count: string
  rows: App.Crm.Data.DomainRowData[]
  footer?: string
  footerHref?: string
}>()
</script>

<template>
  <!-- The mockup's .dom block, filled from any column's rows. Chips state,
       buttons act: the row opens the project, the action is a stroked .abtn
       that dials or opens the screen where the decision is taken. -->
  <div class="dom">
    <div class="dh">
      <b>{{ title }}</b>
      <span class="n">{{ count }}</span>
    </div>

    <div v-for="(row, index) in rows" :key="`${row.projectId}-${index}`" class="dr">
      <Link class="who" :href="`/auftraege/${row.projectId}`">{{ row.who }}</Link>
      <span class="what">
        {{ row.what }}
        <i v-if="row.emphasis">{{ row.emphasis }}</i>
      </span>
      <a
        v-if="row.action"
        class="abtn"
        :class="{ hot: row.hot }"
        :href="row.action.target"
      >{{ row.action.label }}</a>
    </div>

    <Link v-if="footer && footerHref" class="blkfoot" :href="footerHref">{{ footer }}</Link>
  </div>
</template>
```

- [ ] **Step 5: Put the column on the page**

In `Uebersicht.vue`, add `inquiries: App.Crm.Data.DomainRowData[]` to `defineProps`, import the component, and render it inside `.doms` **before** the existing Nächste Shootings block — mockup order is Anfragen & Angebote first:

```vue
    <DomainColumn
      v-if="inquiries.length"
      :title="$t('dashboard.inquiries')"
      :count="$t('dashboard.items', { count: inquiries.length })"
      :rows="inquiries"
      :footer="$t('dashboard.toBoard')"
      footer-href="/anfragen"
    />
```

- [ ] **Step 6: Add the German copy**

In `resources/js/locales/de.json`, under `dashboard`:

```json
    "inquiries": "Anfragen & Angebote",
    "items": "{count} Posten",
    "toBoard": "Board"
```

Terse, per the global constraints. No explanatory sentence.

- [ ] **Step 7: Run the tests and the type check**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
pnpm --filter @studio/app lint
```

Expected: both PASS.

- [ ] **Step 8: Screenshot the screen beside the mockup**

Open `http://localhost:8000/` and `mockups/admin/01-uebersicht.html` at 1440px. The new block sits top-left at 574px wide, `.dh` uppercase, rows at `.dr`, action stroked. Attach both to the PR.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Staff/DashboardController.php resources/js tests/Feature/Crm
git commit -m "feat(dashboard): the Anfragen & Angebote column"
```

---

## Task 5: `ReadPaymentColumn` — S-09's row

**Files:**
- Create: `app/Payments/Actions/ReadPaymentColumn.php`
- Create: `tests/Feature/Payments/PaymentColumnTest.php`

**Interfaces:**
- Consumes: `DomainRowData`, `RowActionData` (Task 2)
- Produces: `ReadPaymentColumn::__invoke(?User $viewer = null): Collection<int, DomainRowData>`

The mockup's second payment row is deliberately absent — see the spec's *Deferred: nie fällig gestellt*. Do not add it.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Models\User;
use App\Payments\Actions\ReadPaymentColumn;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function overdueInstallment(string $name, ?int $notifiedDaysAgo, int $amount = 110000): PaymentInstallment
{
    $project = Project::factory()->create(['name' => $name]);
    $project->attachContact(
        Contact::factory()->create(['phone' => '+49 40 1234567']),
        ProjectContactRole::Primary,
    );
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => $amount]);

    return PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => $amount,
        'due_on' => now()->subDays(20)->toDateString(),
        'overdue_notified_at' => $notifiedDaysAgo === null ? null : now()->subDays($notifiedDaysAgo),
    ]);
}

it('does not row an overdue installment the scan has not yet noticed', function () {
    // No overdue_notified_at means DetectOverdueInstallments has not run
    // against it. The machine has not had its turn.
    overdueInstallment('Sievers–Brandt', notifiedDaysAgo: null);

    expect(app(ReadPaymentColumn::class)())->toBeEmpty();
});

it('does not row an installment dunned only yesterday', function () {
    // Stripe is still working. Three days is when a call stops being a
    // duplicate of what the machine is already doing.
    overdueInstallment('Sievers–Brandt', notifiedDaysAgo: 1);

    expect(app(ReadPaymentColumn::class)())->toBeEmpty();
});

it('rows an installment whose dunning has run out of road', function () {
    overdueInstallment('Sievers–Brandt', notifiedDaysAgo: 9);

    $rows = app(ReadPaymentColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Sievers–Brandt')
        ->and($rows->first()->what)->toContain('Stripe mahnt')
        ->and($rows->first()->emphasis)->toBe('seit 9 Tagen')
        ->and($rows->first()->what)->toContain('1.100,00 €')
        ->and($rows->first()->hot)->toBeTrue()
        ->and($rows->first()->action->kind)->toBe('tel');
});

it('does not row an installment that has since been paid', function () {
    $installment = overdueInstallment('Sievers–Brandt', notifiedDaysAgo: 9);
    $installment->update(['status' => \App\Payments\Enums\PaymentInstallmentStatus::Paid]);

    expect(app(ReadPaymentColumn::class)())->toBeEmpty();
});

it('shows a crew member only their own projects', function () {
    overdueInstallment('Nicht meins', notifiedDaysAgo: 9);

    $crew = User::factory()->secondShooter()->withMfa()->create();

    expect(app(ReadPaymentColumn::class)($crew))->toBeEmpty();
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=PaymentColumn
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Write the reader**

```php
<?php

namespace App\Payments\Actions;

use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Models\Project;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Mockup 01's *Zahlungen* column: money the machine has already chased and
 * not got.
 *
 * One row, not the mockup's two. The other — an installment never issued —
 * has no state to detect and no action to end it with; the design's
 * "Deferred: nie fällig gestellt" records why, and it belongs to a Payments
 * story rather than to this screen.
 *
 * The gate is overdue_notified_at rather than an AutomationRun count:
 * DetectOverdueInstallments already stamps the moment the studio's own scan
 * first noticed, and M-05's Stripe webhook stamps the same field. That is the
 * dunning trail the mockup's row actually describes.
 */
class ReadPaymentColumn
{
    private const DUNNING_EXHAUSTED_DAYS = 3;

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        return PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereNotNull('overdue_notified_at')
            ->where('overdue_notified_at', '<=', now()->subDays(self::DUNNING_EXHAUSTED_DAYS))
            ->whereHas('plan.job', fn ($query) => $query->whereIn(
                'project_id',
                Project::query()->visibleTo($viewer)->select('projects.id'),
            ))
            ->with('plan.job.project.contacts')
            ->orderBy('overdue_notified_at')
            ->get()
            ->map(function (PaymentInstallment $installment) {
                $project = $installment->plan->job->project;
                $days = $installment->overdue_notified_at->diffInDays();

                return new DomainRowData(
                    projectId: $project->public_id,
                    who: $project->name,
                    what: sprintf(
                        'Stripe mahnt erfolglos · %s',
                        Number::currency($installment->amount / 100, 'EUR', 'de'),
                    ),
                    emphasis: "seit {$days} Tagen",
                    action: RowActionData::tel('Anrufen', $project->primaryContact()?->phone),
                    hot: true,
                );
            })
            ->values();
    }
}
```

- [ ] **Step 4: Run the test to green**

```bash
docker exec studio-dev-server php artisan test --filter=PaymentColumn
```

Expected: PASS, 5 tests. If the currency assertion fails on a non-breaking space, assert with `toContain('1.100')` instead of the full string — `Number::currency` formats with the ICU locale and the separator is not worth pinning.

- [ ] **Step 5: Commit**

```bash
git add app/Payments/Actions tests/Feature/Payments
git commit -m "feat(payments): the Zahlungen column reader"
```

---

## Task 6: S-09 on the screen

**Files:**
- Modify: `app/Http/Controllers/Staff/DashboardController.php`
- Modify: `resources/js/admin/pages/Uebersicht.vue`
- Modify: `resources/js/locales/de.json`
- Test: `tests/Feature/Crm/UebersichtTest.php`

**Interfaces:**
- Consumes: `ReadPaymentColumn` (Task 5), `DomainColumn.vue` (Task 4)
- Produces: the `payments` Inertia prop

- [ ] **Step 1: Write the failing test**

```php
it('carries the Zahlungen column', function () {
    $project = Project::factory()->create(['name' => 'Sievers–Brandt']);
    $project->attachContact(Contact::factory()->create(['phone' => '+4940123']), ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 110000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 110000,
        'due_on' => now()->subDays(20)->toDateString(),
        'overdue_notified_at' => now()->subDays(9),
    ]);

    expect(props('/')['payments'])->toHaveCount(1);
});

it('shows no Zahlungen column when nothing is behind', function () {
    expect(props('/')['payments'])->toBeEmpty();
});
```

Add imports `App\Payments\Models\PaymentInstallment`, `App\Payments\Models\PaymentPlan`.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
```

Expected: FAIL — undefined array key `payments`.

- [ ] **Step 3: Add the prop**

```php
    public function __invoke(
        ReadProductionBoard $board,
        ReadInquiryColumn $inquiries,
        ReadPaymentColumn $payments,
    ): Response {
```

and `'payments' => $payments($viewer),` in the render array. Add `use App\Payments\Actions\ReadPaymentColumn;`.

- [ ] **Step 4: Put the column on the page**

In `Uebersicht.vue`, add `payments: App.Crm.Data.DomainRowData[]` to `defineProps` and render after the shootings block — mockup order is Anfragen & Angebote, Nächste Shootings, Zahlungen, Nach der Lieferung:

```vue
    <DomainColumn
      v-if="payments.length"
      :title="$t('dashboard.payments')"
      :count="$t('dashboard.items', { count: payments.length })"
      :rows="payments"
    />
```

- [ ] **Step 5: Add the copy**

```json
    "payments": "Zahlungen"
```

- [ ] **Step 6: Run tests and lint**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
pnpm --filter @studio/app lint
```

Expected: both PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Staff/DashboardController.php resources/js tests/Feature/Crm
git commit -m "feat(dashboard): the Zahlungen column"
```

---

## Task 7: The referrer on a lead

S-10's first enabler. `LeadSource::Referral` records *that* a client was referred and nothing about by whom.

**Files:**
- Create: `database/migrations/2026_08_15_000100_add_referred_by_to_leads_table.php`
- Modify: `app/Crm/Models/Lead.php`
- Modify: `app/Crm/Actions/CaptureInquiry.php`
- Test: `tests/Feature/Crm/InquiryCaptureTest.php` (append)

**Interfaces:**
- Produces: `leads.referred_by_contact_id` (nullable FK to `contacts`), `Lead::referredBy(): BelongsTo`

- [ ] **Step 1: Write the failing test**

`CaptureInquiry::__invoke(CaptureInquiryData $data, ?User $actor): Project` — it returns the Project, and the Lead hangs off it as `$project->lead`.

```php
it('records who referred a client when the source is a referral', function () {
    $referrer = Contact::factory()->create(['name' => 'Familie Osterhoff']);

    $project = app(CaptureInquiry::class)(new CaptureInquiryData(
        contact_id: null,
        contact_name: 'Steffens & Falk',
        contact_email: 'steffens@example.test',
        contact_phone: null,
        partner_name: null,
        project_name: 'Steffens & Falk',
        type: ProjectType::Wedding,
        source: LeadSource::Referral,
        captured_via: CaptureChannel::Phone,
        date: null,
        inquiry_text: null,
        referred_by_contact_id: $referrer->public_id,
    ), $this->staff);

    expect($project->lead->referredBy->is($referrer))->toBeTrue();
});

it('leaves the referrer null when nobody recorded one', function () {
    // A referral whose referrer nobody wrote down is still a referral. A
    // required field would push whoever takes the call into inventing one.
    $project = app(CaptureInquiry::class)(new CaptureInquiryData(
        contact_id: null,
        contact_name: 'Ohne Vermittler',
        contact_email: 'ohne@example.test',
        contact_phone: null,
        partner_name: null,
        project_name: 'Ohne Vermittler',
        type: ProjectType::Wedding,
        source: LeadSource::Referral,
        captured_via: CaptureChannel::Phone,
        date: null,
        inquiry_text: null,
        referred_by_contact_id: null,
    ), $this->staff);

    expect($project->lead->referredBy)->toBeNull();
});
```

Imports: `App\Crm\Actions\CaptureInquiry`, `App\Crm\Data\CaptureInquiryData`, `App\Crm\Enums\CaptureChannel`, `App\Crm\Enums\LeadSource`, `App\Crm\Enums\ProjectType`, `App\Crm\Models\Contact`. If `CaptureChannel` has no `Phone` case, use whichever case the surrounding tests already pass.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=InquiryCapture
```

Expected: FAIL — unknown named argument `referredByContactId`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sent this client. LeadSource::Referral records that a client was
 * referred and nothing about by whom, so the Übersicht cannot say "hat
 * Steffens & Falk empfohlen" and the studio cannot thank anyone.
 *
 * Nullable on purpose: a referral whose referrer nobody wrote down is still a
 * referral, and a required field would push whoever takes the call into
 * inventing one. nullOnDelete rather than cascade — erasing a contact under
 * X-04 must not take the lead that names them with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('referred_by_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_contact_id');
        });
    }
};
```

- [ ] **Step 4: Wire the model and the capture**

In `Lead.php`, add `'referred_by_contact_id'` to `$fillable` and:

```php
    /**
     * The contact who sent this client. Null is the ordinary case — see the
     * migration for why it is not required.
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'referred_by_contact_id');
    }
```

In `CaptureInquiryData`, add `public ?string $referred_by_contact_id` as the last constructor property, and to `rules()`:

```php
            // The CUID2 of an existing contact, same shape as contact_id
            // above. Nullable always, including when the source is a
            // referral — see the migration.
            'referred_by_contact_id' => ['nullable', 'string', 'size:24'],
```

In `CaptureInquiry`, resolve that `public_id` to a `Contact` and pass the id through to the `Lead::create` call:

```php
        $referrer = $data->referred_by_contact_id === null
            ? null
            : Contact::query()->where('public_id', $data->referred_by_contact_id)->first();
```

Resolve by `public_id` only — the global constraint forbids an integer identifier crossing the boundary. `first()` rather than `firstOrFail()`: a referrer who cannot be found is the same case as one nobody recorded, and a 404 on the inquiry form would lose the whole capture over a detail that is optional by design.

- [ ] **Step 5: Run the tests**

```bash
docker exec studio-dev-server php artisan test --filter=InquiryCapture
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Crm tests/Feature/Crm
git commit -m "feat(crm): leads record who referred the client"
```

---

## Task 8: Gallery expiry

S-10's second enabler. No expiry concept exists — `expires_at` today lives only on `gallery_email_verifications`, a different clock.

**Files:**
- Create: `database/migrations/2026_08_15_000200_add_expires_at_to_galleries_table.php`
- Modify: `app/Gallery/Models/Gallery.php`
- Test: `tests/Feature/Gallery/GalleryExpiryTest.php`

**Interfaces:**
- Produces: `galleries.expires_at` (nullable `timestampTz`), cast `immutable_datetime`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps a gallery indefinitely when no expiry is set', function () {
    // Null means kept. Every gallery that exists today is one of these, which
    // is why the migration backfills nothing.
    expect(Gallery::factory()->create()->expires_at)->toBeNull();
});

it('carries an expiry as an immutable datetime', function () {
    $gallery = Gallery::factory()->create(['expires_at' => now()->addDays(30)]);

    expect($gallery->fresh()->expires_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=GalleryExpiry
```

Expected: FAIL — column `expires_at` does not exist.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a delivered gallery stops being available. Distinct from
 * gallery_email_verifications.expires_at, which times out one visitor's proof
 * of address and has nothing to do with how long the photographs stay up.
 *
 * Null means kept indefinitely, which is what every existing gallery is — so
 * this backfills nothing and changes no current behaviour. Only the Übersicht
 * reads it today: a gallery running out is something the studio may want to
 * act on before it does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->timestampTz('expires_at')->nullable();
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
```

- [ ] **Step 4: Add the fillable and the cast**

In `Gallery.php`, add `'expires_at'` to `$fillable` and `'expires_at' => 'immutable_datetime',` to `casts()`.

- [ ] **Step 5: Stamp it at publish**

Without this the column is dead — nothing in the app would ever write it, and the Übersicht row would only ever appear against seeded data.

Create `config/gallery.php`:

```php
<?php

/**
 * How long a published gallery stays up, in days. Null means indefinitely,
 * which is what every gallery published before this config existed is, and
 * stays the default until a studio asks for a limit.
 *
 * Config rather than a studio setting: the settings store is a screen, a
 * form, a resolver and a section of de.json for one nullable integer nobody
 * has asked to edit. When a studio asks, the value moves and Gallery::publish
 * does not change.
 */
return [
    'retention_days' => env('GALLERY_RETENTION_DAYS'),
];
```

In `Gallery::publish()`, inside the existing transaction and before creating the version:

```php
            $retention = config('gallery.retention_days');

            if ($retention !== null && $this->expires_at === null) {
                // Stamped once, on first publish. A second version does not
                // restart the clock: the client has had the photographs since
                // version one, and a re-publish to fix a caption is not a new
                // delivery.
                $this->update(['expires_at' => now()->addDays((int) $retention)]);
            }
```

Add a test for both branches:

```php
it('stamps an expiry at publish when the studio has set a retention', function () {
    config(['gallery.retention_days' => 90]);
    $gallery = Gallery::factory()->create(['assets' => [['key' => 'a.jpg']]]);

    $gallery->publish();

    expect($gallery->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(90)->toDateString());
});

it('does not restart the clock on a second publish', function () {
    config(['gallery.retention_days' => 90]);
    $gallery = Gallery::factory()->create(['assets' => [['key' => 'a.jpg']]]);
    $gallery->publish();
    $first = $gallery->fresh()->expires_at;

    $this->travel(10)->days();
    $gallery->fresh()->publish();

    expect($gallery->fresh()->expires_at->eq($first))->toBeTrue();
});

it('stamps nothing when no retention is configured', function () {
    config(['gallery.retention_days' => null]);
    $gallery = Gallery::factory()->create(['assets' => [['key' => 'a.jpg']]]);

    $gallery->publish();

    expect($gallery->fresh()->expires_at)->toBeNull();
});
```

If `GalleryFactory` does not accept an `assets` array in that shape, mirror whatever the existing gallery publishing tests pass — read `tests/Feature/Gallery/` first.

- [ ] **Step 6: Run the gallery suite**

```bash
docker exec studio-dev-server php artisan test --filter=Gallery
```

Expected: PASS. Nothing existing reads the column, so no other gallery test should move.

- [ ] **Step 7: Commit**

```bash
git add database/migrations config/gallery.php app/Gallery tests/Feature/Gallery
git commit -m "feat(gallery): galleries carry an expiry date, stamped at publish"
```

---

## Task 9: `ReadAfterDeliveryColumn` — S-10's rows

**Files:**
- Create: `app/Gallery/Actions/ReadAfterDeliveryColumn.php`
- Create: `tests/Feature/Gallery/AfterDeliveryColumnTest.php`

**Interfaces:**
- Consumes: `DomainRowData`, `RowActionData` (Task 2); `leads.referred_by_contact_id` (Task 7); `galleries.expires_at` (Task 8)
- Produces: `ReadAfterDeliveryColumn::__invoke(?User $viewer = null): Collection<int, DomainRowData>`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Crm\Enums\LeadSource;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Gallery\Actions\ReadAfterDeliveryColumn;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('rows a booked project whose lead names a referrer', function () {
    $referrer = Contact::factory()->create(['name' => 'Familie Osterhoff', 'phone' => '+49 40 999']);
    $project = Project::factory()->create(['name' => 'Steffens & Falk']);
    Job::factory()->for($project)->create();
    Lead::factory()->for($project)->create([
        'source' => LeadSource::Referral,
        'referred_by_contact_id' => $referrer->id,
    ]);

    $rows = app(ReadAfterDeliveryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Familie Osterhoff')
        ->and($rows->first()->what)->toContain('Steffens & Falk')
        ->and($rows->first()->action->label)->toBe('Bedanken')
        ->and($rows->first()->action->kind)->toBe('tel');
});

it('does not row a referral whose project was never booked', function () {
    // Nothing to thank anyone for yet.
    $referrer = Contact::factory()->create();
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create([
        'source' => LeadSource::Referral,
        'referred_by_contact_id' => $referrer->id,
    ]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('does not row a referral nobody wrote a referrer down for', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Lead::factory()->for($project)->create([
        'source' => LeadSource::Referral,
        'referred_by_contact_id' => null,
    ]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('rows a gallery running out, pointing at the gallery', function () {
    $project = Project::factory()->create(['name' => "Hensel Hochzeit '25"]);
    $gallery = Gallery::factory()->for($project)->create([
        'expires_at' => now()->addDays(12),
    ]);

    $rows = app(ReadAfterDeliveryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->what)->toContain('Galerie läuft')
        ->and($rows->first()->action->label)->toBe('Album anbieten')
        ->and($rows->first()->action->kind)->toBe('link')
        ->and($rows->first()->action->target)->toBe("/galerien/{$gallery->public_id}/medien");
});

it('does not row a gallery kept indefinitely', function () {
    Gallery::factory()->create(['expires_at' => null]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('does not row a gallery whose expiry is far off', function () {
    Gallery::factory()->create(['expires_at' => now()->addDays(200)]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('shows a crew member only their own projects', function () {
    $project = Project::factory()->create();
    Gallery::factory()->for($project)->create(['expires_at' => now()->addDays(12)]);

    $crew = User::factory()->secondShooter()->withMfa()->create();

    expect(app(ReadAfterDeliveryColumn::class)($crew))->toBeEmpty();
});
```

`routes/admin.php` registers no bare gallery detail screen — `galleries.media` at `/galerien/{gallery}/medien` is the gallery's own screen, and that is where "Album anbieten" goes.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=AfterDeliveryColumn
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Write the reader**

```php
<?php

namespace App\Gallery\Actions;

use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Enums\LeadSource;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Mockup 01's *Nach der Lieferung* column: the occasions the mockup files as
 * "kept deliberately personal".
 *
 * Neither row sends anything, and neither is a thread. E-06 makes the client
 * the only party who can originate a conversation — stated in StartThread's
 * own docblock, in PostMessage's, and beside the route — so a thank-you here
 * is a phone call, which is the truer action anyway. A thank-you the machine
 * sends is not a thank-you.
 *
 * The mockup's "— Album nie angeboten" clause is absent: no album concept
 * exists to have been offered, and inventing one to fill a clause is the
 * failure clickdummy §6 records for this screen. The row states the expiry,
 * and the button opens the gallery.
 */
class ReadAfterDeliveryColumn
{
    private const EXPIRY_WINDOW_DAYS = 30;

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        $visible = Project::query()->visibleTo($viewer)->select('projects.id');

        return $this->referrals($visible)->concat($this->expiringGalleries($visible))->values();
    }

    /**
     * A referral whose project actually became work. Joined through `jobs` for
     * the same reason ReadProductionBoard is: before the Job exists the
     * project is a pursuit, and thanking someone for a pursuit is premature.
     *
     * @return Collection<int, DomainRowData>
     */
    private function referrals($visible): Collection
    {
        return Lead::query()
            ->where('source', LeadSource::Referral)
            ->whereNotNull('referred_by_contact_id')
            ->whereIn('project_id', $visible)
            ->whereHas('project.job')
            ->with(['project', 'referredBy'])
            ->get()
            ->map(fn (Lead $lead) => new DomainRowData(
                projectId: $lead->project->public_id,
                who: $lead->referredBy->name,
                what: 'hat '.$lead->project->name.' empfohlen',
                emphasis: null,
                action: RowActionData::tel('Bedanken', $lead->referredBy->phone),
            ));
    }

    /**
     * @return Collection<int, DomainRowData>
     */
    private function expiringGalleries($visible): Collection
    {
        return Gallery::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(self::EXPIRY_WINDOW_DAYS))
            ->whereIn('project_id', $visible)
            ->with('project')
            ->orderBy('expires_at')
            ->get()
            ->map(fn (Gallery $gallery) => new DomainRowData(
                projectId: $gallery->project->public_id,
                who: $gallery->project->name,
                what: 'Galerie läuft',
                emphasis: 'am '.$gallery->expires_at->format('d.m.').' ab',
                action: RowActionData::link('Album anbieten', "/galerien/{$gallery->public_id}/medien"),
            ));
    }
}
```

- [ ] **Step 4: Run the test to green**

```bash
docker exec studio-dev-server php artisan test --filter=AfterDeliveryColumn
```

Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Gallery/Actions tests/Feature/Gallery
git commit -m "feat(gallery): the Nach der Lieferung column reader"
```

---

## Task 10: S-10 on the screen

**Files:**
- Modify: `app/Http/Controllers/Staff/DashboardController.php`
- Modify: `resources/js/admin/pages/Uebersicht.vue`
- Modify: `resources/js/locales/de.json`
- Test: `tests/Feature/Crm/UebersichtTest.php`

**Interfaces:**
- Consumes: `ReadAfterDeliveryColumn` (Task 9), `DomainColumn.vue` (Task 4)
- Produces: the `afterDelivery` Inertia prop

- [ ] **Step 1: Write the failing test**

```php
it('carries the Nach der Lieferung column', function () {
    $project = Project::factory()->create(['name' => "Hensel Hochzeit '25"]);
    Gallery::factory()->for($project)->create(['expires_at' => now()->addDays(12)]);

    expect(props('/')['afterDelivery'])->toHaveCount(1);
});

it('shows no Nach der Lieferung column when no occasion is open', function () {
    expect(props('/')['afterDelivery'])->toBeEmpty();
});
```

Add `use App\Gallery\Models\Gallery;`.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
```

Expected: FAIL — undefined array key `afterDelivery`.

- [ ] **Step 3: Add the prop, the column and the copy**

Controller: add `ReadAfterDeliveryColumn $afterDelivery` to the signature and `'afterDelivery' => $afterDelivery($viewer),` to the render.

`Uebersicht.vue`, last in `.doms`:

```vue
    <DomainColumn
      v-if="afterDelivery.length"
      :title="$t('dashboard.afterDelivery')"
      :count="$t('dashboard.occasions', { count: afterDelivery.length })"
      :rows="afterDelivery"
    />
```

`de.json`:

```json
    "afterDelivery": "Nach der Lieferung",
    "occasions": "{count} Anlässe"
```

- [ ] **Step 4: Run tests and lint**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
pnpm --filter @studio/app lint
```

Expected: both PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Staff/DashboardController.php resources/js tests/Feature/Crm
git commit -m "feat(dashboard): the Nach der Lieferung column"
```

---

## Task 11: The KPI row and the unanswered link

**Files:**
- Modify: `app/Http/Controllers/Staff/DashboardController.php`
- Modify: `resources/js/admin/pages/Uebersicht.vue`
- Modify: `resources/js/locales/de.json`
- Test: `tests/Feature/Crm/UebersichtTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks
- Produces: props `pipeline: int` (minor units), `overdueTotal: int` (minor units), `acceptanceRate: ?int`, `unanswered: int`

- [ ] **Step 1: Write the failing test**

```php
it('sums the pipeline from quotes still in play', function () {
    // Draft and sent are in play; accepted is won and superseded is gone.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'total' => 4875000]);
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted, 'total' => 900000]);

    expect(props('/')['pipeline'])->toBe(4875000);
});

it('sums what is overdue', function () {
    $job = Job::factory()->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 290000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 290000,
        'due_on' => now()->subDays(5)->toDateString(),
    ]);

    expect(props('/')['overdueTotal'])->toBe(290000);
});

it('reports the acceptance rate over the trailing twelve months', function () {
    $project = Project::factory()->create();
    Quote::factory()->for($project)->count(5)->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now()->subMonths(3),
    ]);
    Quote::factory()->for($project)->count(3)->create([
        'status' => QuoteStatus::Accepted,
        'sent_at' => now()->subMonths(3),
        'accepted_at' => now()->subMonths(2),
    ]);
    // Older than the window; must not move the number.
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'sent_at' => now()->subMonths(18),
        'accepted_at' => now()->subMonths(17),
    ]);

    expect(props('/')['acceptanceRate'])->toBe(38);
});

it('reports no acceptance rate when nothing has been sent', function () {
    // A percentage of nothing is not zero percent; it is no number at all,
    // and the tile does not render.
    expect(props('/')['acceptanceRate'])->toBeNull();
});

it('counts threads where the client spoke last', function () {
    // Unbeantwortet, not ungelesen: ThreadParticipant carries no last_read_at
    // and this does not add one. An unanswered thread is a fact about the
    // conversation; an unread one is a fact about a person.
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();

    $waiting = Thread::factory()->for($project)->create();
    Message::factory()->for($waiting)->create(['contact_id' => $contact->id, 'author_id' => null]);

    $answered = Thread::factory()->for($project)->create();
    Message::factory()->for($answered)->create(['contact_id' => $contact->id, 'author_id' => null]);
    Message::factory()->for($answered)->create(['contact_id' => null, 'author_id' => $this->staff->id]);

    expect(props('/')['unanswered'])->toBe(1);
});
```

Add imports `App\Engagement\Models\Message`, `App\Engagement\Models\Thread`.

- [ ] **Step 2: Run it and watch it fail**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
```

Expected: FAIL — undefined array key `pipeline`.

- [ ] **Step 3: Add the four figures**

In `DashboardController`, add to the render array and add these private methods:

```php
    /**
     * What is still in play. Draft and sent only: accepted is won and belongs
     * to a revenue number, superseded and expired are gone. Minor units all
     * the way to the browser, formatted once on the screen.
     */
    private function pipeline(): int
    {
        return (int) Quote::query()
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent])
            ->sum('total');
    }

    private function overdueTotal(): int
    {
        return (int) PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereDate('due_on', '<', now()->toDateString())
            ->sum('amount');
    }

    /**
     * Accepted over sent across the trailing twelve months, by the date the
     * quote went out rather than the date it was accepted — otherwise a quote
     * sent in month eleven and accepted in month thirteen counts in a window
     * its own denominator is not in.
     *
     * Null rather than zero when nothing has been sent: a percentage of
     * nothing is not zero percent, and the tile does not render.
     */
    private function acceptanceRate(): ?int
    {
        $since = now()->subYear();

        $sent = Quote::query()->whereNotNull('sent_at')->where('sent_at', '>=', $since)->count();

        if ($sent === 0) {
            return null;
        }

        $accepted = Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $since)
            ->count();

        return (int) round($accepted / $sent * 100);
    }

    /**
     * Threads whose newest message came from the client. Unbeantwortet, not
     * ungelesen — see the design's Part 4.
     */
    private function unanswered(): int
    {
        return Thread::query()
            ->whereHas('messages', fn ($query) => $query
                ->whereNotNull('contact_id')
                ->whereRaw('messages.created_at = (select max(m2.created_at) from messages m2 where m2.thread_id = messages.thread_id)'))
            ->count();
    }
```

- [ ] **Step 4: Put the tiles and the link on the page**

`Uebersicht.vue`: add the four props to `defineProps`, and render the tiles in mockup order — Pipeline, überfällig, nächstes Shooting, Angebote angenommen. Each is `v-if`-guarded on its own figure so a tile with nothing behind it is absent rather than a zero, per S-01's rule. Format money with `Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })` over `value / 100`.

The header link goes in `.hrow` between the heading and the button, exactly as the mockup has it:

```vue
      <Link v-if="unanswered" class="txtbtn" href="/anfragen" style="margin-left: auto">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v13H8l-4 4z" /><path d="M8 9h8M8 13h5" /></svg>
        {{ $t('dashboard.unanswered', { count: unanswered }) }}
      </Link>
```

`.txtbtn` no longer exists as a CSS class — it is `Button`'s `ghost` variant (see the note in `tokens.css`). Use `<Button :as="Link" variant="ghost">` and keep the `margin-left: auto`.

- [ ] **Step 5: Add the copy**

```json
    "pipeline": "Pipeline",
    "overdue": "überfällig",
    "acceptance": "Angebote angenommen",
    "lastTwelveMonths": "letzte 12 Monate",
    "unanswered": "{count} unbeantwortet"
```

- [ ] **Step 6: Run tests and lint**

```bash
docker exec studio-dev-server php artisan test --filter=Uebersicht
pnpm --filter @studio/app lint
```

Expected: both PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Staff/DashboardController.php resources/js tests/Feature/Crm
git commit -m "feat(dashboard): the four-tile KPI row and the unanswered link"
```

---

## Task 12: The seeder grows what the columns need

Every column now renders from data. `DemoCrmSeeder` must put at least one row in each, or the screen cannot be held next to the mockup.

**Files:**
- Modify: `database/seeders/DemoCrmSeeder.php`

**Interfaces:**
- Consumes: everything above

- [ ] **Step 1: Seed the five conditions**

Add a `seedDashboardOccasions()` method, called at the end of `run()`, building through the real models:

1. A sent quote on an existing project, `valid_until` five days out, plus two `AutomationRun` rows fired through `FireAutomationRule` against that project with the `quote-follow-up` rule — so the row reads *2× erinnert* because it was reminded twice, not because a string says so.
2. An accepted quote on another project with no contract.
3. A rendered contract, six days old, no signature.
4. An overdue installment with `overdue_notified_at` nine days ago.
5. A referral: a booked project whose lead names an existing contact, and a gallery on a delivered project with `expires_at` twelve days out.

Seed the automation rules first — `$this->call(...)` is not available for a console command, so call `SeedDefaultAutomationRulesCommand` through `Artisan::call('automation:seed-default-rules')` before firing any run.

- [ ] **Step 2: Reseed and look at the screen**

```bash
docker exec studio-dev-server php artisan migrate:fresh --seed
```

Then open `http://localhost:8000/`, sign in through Dev-Login, and confirm all four columns render with rows.

- [ ] **Step 3: Hold it next to the mockup**

Open `mockups/admin/01-uebersicht.html` at 1440px beside the built screen. Check: `.kpis` is four tiles at 283.5px, `.doms` is two columns at 574px, each `.dom` heading uppercase with its `.n` chip right-aligned, rows `.dr` with `.who` / `.what` / stroked `.abtn`, `.abtn.hot` on the overdue ones. Screenshot both.

- [ ] **Step 4: Run everything**

```bash
docker exec studio-dev-server php artisan test
pnpm --filter @studio/app lint
```

Expected: PASS, except `Tests\Feature\Contracts\ContractViewingTest` — two failures there pre-date this work and are unrelated. Confirm by stashing and re-running if they look new.

- [ ] **Step 5: Commit**

```bash
git add database/seeders
git commit -m "feat(seeder): demo data for every Übersicht column"
```

---

## Issue Mapping

Four GitHub issues on a new milestone *Sprint 22 — The Übersicht completes*, each closing when its tasks land.

| issue | tasks | note |
| --- | --- | --- |
| `S-08` — Anfragen & Angebote column | 1, 2, 3, 4 | carries the shared row shape and the quote-validity anchor |
| `S-09` — Zahlungen column | 5, 6 | body names *nie fällig gestellt* as deferred and why |
| `S-10` — Nach der Lieferung column | 7, 8, 9, 10 | carries its two enablers |
| `S-11` — KPI row and the unanswered link | 11, 12 | the seeder lands here, once every column exists to seed for |
