<?php

namespace App\Automation\Http\Controllers;

use App\Automation\Data\AutomationRuleData;
use App\Automation\Data\AutomationRunData;
use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationCondition;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use App\Automation\Notifications\AutomationReminder;
use App\Concerns\AuthorizesStaffSettings;
use App\Crm\Models\ClientCase;
use App\Crm\Models\ProjectType;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Models\User;
use App\Notifications\Support\MailPreview;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Support\InvoiceMergeData;
use App\Portal\Support\ClientAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Visus\Cuid2\Cuid2;

/**
 * E-14: the screen E-08's rules never had. Until this controller they could
 * only be created by SeedDefaultAutomationRulesCommand, which meant a studio
 * could read its own automation in config and nowhere else.
 */
class AutomationRuleController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Automations', [
            'rules' => AutomationRuleData::collect(
                AutomationRule::query()->withCount(['runs as drafts_count' => fn ($runs) => $runs->where('status', AutomationRunStatus::AwaitingApproval)])->orderBy('name')->get()->map(AutomationRuleData::fromModel(...))
            ),
            // Der Sweep laeuft in Studiozeit, nicht in Containerzeit. Wer
            // die Spalte "letzter Lauf" liest, liest sie auf dieser Uhr.
            'timezone' => StudioSettings::current()->localTimezone(),
        ]);
    }

    public function edit(AutomationRule $automationRule): Response
    {
        $this->authorizeSettings();
        $automationRule->loadCount(['runs as drafts_count' => fn ($runs) => $runs->where('status', AutomationRunStatus::AwaitingApproval)]);

        return Inertia::render('Settings/AutomationRule', [
            'rule' => AutomationRuleData::fromModel($automationRule),
            'eventClause' => $automationRule->event_class === null ? null : __('settings.event_clauses.'.class_basename($automationRule->event_class)),
            'anchors' => array_map(
                fn (AutomationAnchor $anchor) => [...$this->options([$anchor])[0], 'noun' => $anchor->noun(), 'at' => $anchor->at(), 'canBefore' => $anchor->canBefore()],
                AutomationAnchor::cases(),
            ),
            'recipients' => $this->options(array_values(array_filter(
                AutomationRecipient::cases(),
                fn (AutomationRecipient $case) => $case !== AutomationRecipient::Crew || ! User::soloStudio(),
            ))),
            'conditions' => array_map(
                fn (AutomationCondition $condition) => [...$this->options([$condition])[0], 'clause' => $condition->clause()],
                AutomationCondition::cases(),
            ),
            // A client rule is always a draft; only the studio side has a choice.
            'actions' => $this->options([AutomationAction::Notice, AutomationAction::Task]),
            'projectTypes' => ProjectType::query()->active()->orderBy('sort_order')->get()
                ->map(fn (ProjectType $type) => ['value' => $type->key, 'label' => $type->name])
                ->all(),
            // The same vocabulary and the same sample values the document
            // templates show, so a field means one thing in both places.
            'mergeFields' => self::mergeFields(),
            'previewProjects' => $this->previewProjects($automationRule->project_type, $automationRule->anchor === AutomationAnchor::InstallmentDue),
            'testRecipient' => auth('staff')->user()->email,
            'runs' => AutomationRunData::collect(
                $automationRule->runs()->with('anchorable')->latest('fired_at')->limit(20)->get()
                    ->map(AutomationRunData::fromModel(...))
            ),
        ]);
    }

    public function store(): RedirectResponse
    {
        $this->authorizeSettings();

        $informal = StudioSettings::current()->client_register === 'informal';

        $rule = AutomationRule::create([
            'name' => 'Neue Regel',
            'anchor' => AutomationAnchor::PrimaryShoot,
            'offset_days' => 7,
            'direction' => AutomationOffsetDirection::Before,
            'recipient_type' => AutomationRecipient::Client,
            'action' => AutomationAction::MailDraft,
            'is_active' => false,
            'notification_subject' => 'Wir sehen uns am {{ shoot.date }}',
            'notification_body' => ($informal ? 'Hallo' : 'Guten Tag')." {{ contact.name }},\n\nam {{ shoot.date }} ist es so weit, und wir freuen uns schon darauf.",
            // The key is the seeder's join column, never a studio's to pick.
            'key' => 'custom-'.(new Cuid2(12)),
            'trigger_type' => AutomationTriggerType::DateOffset,
        ]);

        return redirect("/einstellungen/automationen/{$rule->public_id}");
    }

    public function update(Request $request, AutomationRule $automationRule): RedirectResponse
    {
        $this->authorizeSettings();

        $automationRule->update($this->validated($request, $automationRule->trigger_type));

        return back();
    }

    public function preview(Request $request, AutomationRule $automationRule, DocumentMergeData $merge, ClientAudience $clients): JsonResponse
    {
        $this->authorizeSettings();

        $validator = Validator::make($request->query(), [
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'project' => ['nullable', 'string', Rule::exists(ClientCase::class, 'public_id')],
            'anchor' => ['nullable', Rule::enum(AutomationAnchor::class)],
            'project_type' => ['nullable', 'string', Rule::exists(ProjectType::class, 'key')],
            'q' => ['nullable', 'string', 'max:100'],
            'recipient' => ['nullable', Rule::enum(AutomationRecipient::class)],
        ], [], __('settings.fields'));

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $input = $validator->validated();
        $data = $this->previewData($input, $merge);
        $reminder = $this->reminder($input, $data);
        $recipient = AutomationRecipient::tryFrom($input['recipient'] ?? '') ?? $automationRule->recipient_type;

        return response()->json([
            'draft' => $recipient === AutomationRecipient::Client,
            'to' => $recipient === AutomationRecipient::Client
                ? $this->clientNames($input['project'] ?? null, $data, $clients)
                : $recipient->label(),
            'subject' => $reminder->subject(),
            'html' => MailPreview::html($reminder, (object) []),
            'samples' => collect(self::mergeFields())
                ->mapWithKeys(fn (array $field) => [$field['path'] => data_get($data, $field['path'])])
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (string) $value)
                ->all(),
            ...($request->query->has('project_type') || $request->query->has('anchor')
                ? ['projects' => $this->previewProjects($input['project_type'] ?? null, ($input['anchor'] ?? null) === AutomationAnchor::InstallmentDue->value, $input['q'] ?? null, $input['project'] ?? null)]
                : []),
        ]);
    }

    public function testMail(Request $request, AutomationRule $automationRule, DocumentMergeData $merge): RedirectResponse
    {
        $this->authorizeSettings();

        $knownFields = $this->knownFields($request->input('anchor') === AutomationAnchor::InstallmentDue->value);
        $input = $request->validate([
            'subject' => ['required', 'string', 'max:255', $knownFields],
            'body' => ['required', 'string', 'max:5000', $knownFields],
            'project' => ['nullable', 'string', Rule::exists(ClientCase::class, 'public_id')],
            'anchor' => ['nullable', Rule::enum(AutomationAnchor::class)],
            'project_type' => ['nullable', 'string', Rule::exists(ProjectType::class, 'key')],
        ], [], __('settings.fields'));
        $user = auth('staff')->user();

        // Straight to the mail channel: a test is neither held back by the
        // user's notification preferences nor an entry in their inbox.
        Notification::sendNow($user, $this->reminder($input, $this->previewData($input, $merge)), ['mail']);

        return back()->with('status', __('settings.test_mail_sent', ['email' => $user->email]));
    }

    public function destroy(AutomationRule $automationRule): RedirectResponse
    {
        $this->authorizeSettings();

        $automationRule->delete();

        return redirect('/einstellungen/automationen');
    }

    /**
     * An event rule carries no anchor and no offset — AutomationRule::booted()
     * refuses one that does, so requiring them here would make the seeded
     * Buchungsbestaetigung unsaveable from its own screen.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, AutomationTriggerType $trigger): array
    {
        $dated = $trigger === AutomationTriggerType::DateOffset;
        $knownFields = $this->knownFields($dated && $request->input('anchor') === AutomationAnchor::InstallmentDue->value);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'anchor' => [Rule::requiredIf($dated), Rule::enum(AutomationAnchor::class)],
            'offset_days' => [Rule::requiredIf($dated), 'integer', 'between:0,365'],
            'direction' => [Rule::requiredIf($dated), Rule::enum(AutomationOffsetDirection::class), function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                $anchor = AutomationAnchor::tryFrom((string) $request->input('anchor'));

                if ($anchor && ! $anchor->canBefore() && $value === AutomationOffsetDirection::Before->value && (int) $request->input('offset_days') > 0) {
                    $fail(__('settings.anchor_only_after', ['noun' => $anchor->noun()]));
                }
            }],
            'recipient_type' => ['required', Rule::enum(AutomationRecipient::class)],
            'action' => ['sometimes', Rule::enum(AutomationAction::class)],
            'condition' => ['nullable', Rule::enum(AutomationCondition::class)],
            'project_type' => ['nullable', 'string', Rule::exists(ProjectType::class, 'key')],
            'notification_subject' => ['required', 'string', 'max:255', $knownFields],
            'notification_body' => ['required', 'string', 'max:5000', $knownFields],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function knownFields(bool $invoiceAllowed): \Closure
    {
        $known = array_column(self::mergeFields(), 'path');
        $name = function (string $path): string {
            return Lang::has("settings.merge_fields.{$path}") ? __("settings.merge_fields.{$path}") : $path;
        };
        $list = fn (array $paths) => rtrim(implode(', ', array_map($name, array_values(array_unique($paths)))), '.');

        return function (string $attribute, mixed $value, \Closure $fail) use ($known, $invoiceAllowed, $list) {
            if ($unknown = MergeFieldVocabulary::unknownPathsIn((string) $value, $known)) {
                return $fail(__('settings.unknown_merge_fields', ['fields' => $list($unknown)]));
            }

            $unavailable = array_filter(
                MergeFieldVocabulary::pathsIn((string) $value),
                fn (string $path) => ! $invoiceAllowed && str_starts_with($path, 'invoice.'),
            );

            if ($unavailable) {
                $fail(__('settings.unavailable_merge_fields', ['fields' => $list($unavailable)]));
            }
        };
    }

    /**
     * @param  array{project?: ?string, anchor?: ?string, project_type?: ?string}  $input
     * @return array<string, mixed>
     */
    private function previewData(array $input, DocumentMergeData $merge): array
    {
        $invoiceAllowed = ($input['anchor'] ?? null) === AutomationAnchor::InstallmentDue->value;
        $data = MergeFieldVocabulary::sample();
        // Header and footer render the real studio; the body must agree.
        $data['studio'] = array_replace($data['studio'], $merge->forStudio()['studio']);

        if ($type = ProjectType::query()->where('key', $input['project_type'] ?? null)->value('name')) {
            $data['project']['name'] = str_replace($data['project']['type'], $type, $data['project']['name']);
            $data['project']['type'] = $type;
        }

        $project = ClientCase::query()->where('public_id', $input['project'] ?? null)
            ->when($input['project_type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->first();

        if ($project) {
            // forCase() blanks the invoice block; an instalment anchor gets
            // the project's next open invoice, as FireAutomationRule does.
            $invoice = $invoiceAllowed
                ? $project->paymentPlan?->installments()
                    ->whereIn('status', [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced])
                    ->whereNotNull('invoice_id')->orderBy('due_on')->first()?->invoice
                : null;
            $data = $invoice ? app(InvoiceMergeData::class)->forInvoice($invoice) : $merge->forCase($project);
            $invoiceAllowed = $invoice !== null;
        }

        // Unset, not blanked: keepUnknown then leaves the field standing so
        // the editor can mark what this trigger cannot fill.
        if (! $invoiceAllowed) {
            unset($data['invoice']);
        }

        return $data;
    }

    /** The picked project's reachable client contacts, the sample contact without one. */
    private function clientNames(?string $project, array $data, ClientAudience $clients): ?string
    {
        if ($project === null) {
            return $data['contact']['name'];
        }

        return $clients->forCase(ClientCase::query()->where('public_id', $project)->first())->pluck('name')->join(', ') ?: null;
    }

    /** @param  array{subject?: ?string, body?: ?string}  $input */
    private function reminder(array $input, array $data): AutomationReminder
    {
        return new AutomationReminder(
            MergeFieldResolver::resolve($input['subject'] ?? '', $data, keepUnknown: true),
            MergeFieldResolver::resolve($input['body'] ?? '', $data, keepUnknown: true),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function previewProjects(?string $type, bool $withOpenInvoice, ?string $search = null, ?string $picked = null): array
    {
        $matching = fn () => ClientCase::query()
            ->when($type, fn ($query, string $type) => $query->where('type', $type))
            ->when($withOpenInvoice, fn ($query) => $query->whereHas('paymentPlan.installments', fn ($installments) => $installments
                ->whereIn('status', [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced])
                ->whereNotNull('invoice_id')));

        $cases = $matching()
            ->when($search, fn ($query, string $term) => $query->where('name', 'ilike', "%{$term}%"))
            ->latest()->limit(10)->get();

        // The picked project outlives the search and the latest ten, or the
        // next refresh would drop the project the person is looking at.
        if ($picked && ! $cases->contains('public_id', $picked) && $case = $matching()->where('public_id', $picked)->first()) {
            $cases->prepend($case);
        }

        return [
            ['value' => '', 'label' => __('settings.preview_sample')],
            ...$cases->map(fn (ClientCase $case) => ['value' => $case->public_id, 'label' => $case->name])->all(),
        ];
    }

    /**
     * Everything FireAutomationRule can resolve; no credit note ever reaches
     * a rule. `needsPayment` marks the fields only a payment instalment's
     * anchor carries.
     *
     * @return list<array{path: string, sample: string, group: string, label: string, needsPayment: bool}>
     */
    private static function mergeFields(): array
    {
        $studio = app(DocumentMergeData::class)->forStudio()['studio'];

        return collect(MergeFieldVocabulary::samples())
            ->reject(fn (string $sample, string $path) => str_starts_with($path, 'creditNote.') || $path === 'studio.logoPath')
            ->map(fn (string $sample, string $path) => [
                'path' => $path,
                'sample' => str_starts_with($path, 'studio.') ? (string) ($studio[substr($path, 7)] ?? $sample) : $sample,
                'group' => __('settings.merge_groups.'.strtok($path, '.')),
                'label' => Lang::has("settings.merge_fields.{$path}") ? __("settings.merge_fields.{$path}") : $path,
                'needsPayment' => str_starts_with($path, 'invoice.'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<AutomationAnchor|AutomationRecipient|AutomationCondition|AutomationAction>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
