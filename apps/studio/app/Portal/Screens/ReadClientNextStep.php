<?php

namespace App\Portal\Screens;

use App\Crm\Enums\FormFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormInstance;
use App\Enums\ContractMode;
use App\Models\StudioSettings;
use App\Payments\Data\PaymentInstallmentData;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Support\PaymentSettings;
use App\Portal\Support\PortalSettings;
use App\Quotes\Enums\QuoteStatus;
use Illuminate\Support\Collection;

/**
 * What the client can do on a project right now, most pressing first. A step
 * only exists when its screen offers the action: a quote without acceptance
 * or a contract without online signing would lead nowhere.
 */
class ReadClientNextStep
{
    public function __construct(private readonly PortalSettings $settings) {}

    /** @return list<array{kind: string, label: ?string, detail: ?string, amount: ?int, dueOn: ?string, overdue: bool, href: string}> */
    public function __invoke(ClientCase $project, Contact $contact, ?PaymentPlanData $plan, array $questionnaires): array
    {
        $base = "/projekte/{$project->public_id}";
        $steps = [];

        $contract = $project->latestContract;

        if ($contract !== null
            && $contract->signature === null
            && StudioSettings::current()->contractMode() !== ContractMode::Off
            && $this->settings->signContractsEnabled($contact)) {
            $steps[] = [0, $this->step('contract', href: "{$base}/vertrag")];
        }

        $quote = $project->latestQuote;

        if ($quote?->status === QuoteStatus::Sent
            && ! $project->isBooked()
            && ($quote->valid_until === null || ! $quote->valid_until->copy()->endOfDay()->isPast())
            && $this->settings->acceptQuotesEnabled($contact)) {
            $steps[] = [2, $this->step('quote', amount: $quote->gross(), dueOn: $quote->valid_until?->format('Y-m-d'), href: "{$base}/angebot")];
        }

        $installment = $this->settings->paymentStatusEnabled($contact) ? $this->nextInstallment($plan) : null;

        if ($installment !== null && PaymentSettings::payableNow($installment)) {
            $steps[] = [$installment->isLate ? 1 : 3, $this->step(
                'installment',
                label: $installment->label,
                amount: $installment->remaining,
                dueOn: $installment->dueOn,
                overdue: $installment->isLate,
                href: "{$base}/zahlungen",
            )];
        }

        foreach ($questionnaires as $sheet) {
            if ($sheet['openCount'] > 0) {
                $steps[] = [4, $this->step(
                    'questionnaire',
                    detail: "{$sheet['name']} · {$sheet['openCount']}/{$sheet['count']}",
                    href: "{$base}/fragebogen/{$sheet['id']}",
                )];
            }
        }

        return collect($steps)->sortBy(fn (array $pair) => $pair[0])->pluck(1)->values()->all();
    }

    /** The next shoot still ahead, for the card that has no step to offer. */
    public function appointment(ClientCase $project): ?array
    {
        $shoot = $project->shoots()->uncancelled()
            ->whereDate('date', '>=', today())
            ->orderBy('date')->orderBy('call_time')
            ->first();

        return $shoot === null ? null : [
            'title' => $shoot->label ?? $shoot->kind,
            'date' => $shoot->date->format('Y-m-d'),
            'callTime' => $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            'venue' => $shoot->venue(),
        ];
    }

    /** The questionnaires the studio shared; an open count is what a row says instead of a progress bar. */
    public function questionnaires(ClientCase $project): array
    {
        return $this->questionnairesFor(collect([$project]))[$project->id] ?? [];
    }

    /**
     * One query for any number of projects.
     *
     * @param  Collection<int, ClientCase>  $projects
     * @return array<int, list<array{id: string, name: string, count: int, openCount: int}>>
     */
    public function questionnairesFor(Collection $projects): array
    {
        return FormInstance::query()
            ->shared()
            ->whereIn('case_id', $projects->pluck('id'))
            ->with('fields.currentAnswer')
            ->get()
            ->groupBy('case_id')
            ->map(fn (Collection $instances) => $instances->map(fn (FormInstance $instance) => [
                'id' => $instance->public_id,
                'name' => $instance->name,
                'count' => $instance->fields->where('type', '!=', FormFieldType::Section)->count(),
                'openCount' => $instance->fields
                    ->where('type', '!=', FormFieldType::Section)
                    ->filter(fn ($field) => $field->currentAnswer?->value === null)
                    ->count(),
            ])->all())
            ->all();
    }

    private function nextInstallment(?PaymentPlanData $plan): ?PaymentInstallmentData
    {
        return collect($plan?->installments ?? [])
            ->first(fn (PaymentInstallmentData $one) => in_array($one->status, array_column(PaymentInstallmentStatus::open(), 'value'), true));
    }

    private function step(string $kind, ?string $label = null, ?string $detail = null, ?int $amount = null, ?string $dueOn = null, bool $overdue = false, string $href = ''): array
    {
        return compact('kind', 'label', 'detail', 'amount', 'dueOn', 'overdue', 'href');
    }
}
