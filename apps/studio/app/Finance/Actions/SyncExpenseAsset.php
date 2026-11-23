<?php

namespace App\Finance\Actions;

use App\Finance\Enums\DepreciationMethod;
use App\Finance\Models\Expense;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Finance\Support\AfaCategories;
use App\Finance\Support\Depreciation;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Keeps the one asset an expense may carry in step with the expense: made when
 * the expense is carried as an asset, changed with it, removed when that is
 * switched off. The business share always follows the expense.
 */
class SyncExpenseAsset
{
    private const FIELDS = ['name', 'acquired_at', 'cost_net', 'cost_gross', 'method', 'useful_life_months', 'afa_category'];

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix, bool $required): array
    {
        $required = $required ? ['required'] : ['nullable'];

        return [
            "{$prefix}name" => [...$required, 'string', 'max:200'],
            "{$prefix}acquired_at" => [...$required, 'date'],
            "{$prefix}cost_net" => [...$required, 'integer', 'min:0'],
            "{$prefix}cost_gross" => [...$required, 'integer', "gte:{$prefix}cost_net"],
            "{$prefix}method" => [...$required, Rule::enum(DepreciationMethod::class)],
            "{$prefix}useful_life_months" => ['nullable', 'integer', 'between:1,600'],
            "{$prefix}afa_category" => ['nullable', Rule::in(array_column(AfaCategories::options(), 'key'))],
        ];
    }

    /** Refuses a GWG above the limit and a linear asset without a life, once the plain rules held. */
    public static function check(Validator $validator, array $asset, string $prefix): void
    {
        $errors = $validator->errors();

        if ($errors->hasAny(["{$prefix}method", "{$prefix}acquired_at", "{$prefix}cost_net", "{$prefix}cost_gross"])) {
            return;
        }

        $method = DepreciationMethod::from($asset['method']);
        $basis = self::basis((int) CarbonImmutable::parse($asset['acquired_at'])->year, (int) $asset['cost_net'], (int) $asset['cost_gross']);

        if ($method === DepreciationMethod::Gwg && ! Depreciation::isGwg($basis)) {
            $errors->add("{$prefix}method", __('finance.gwgLimit'));
        }

        if ($method === DepreciationMethod::Linear && self::months($asset) === null) {
            $validator->addFailure("{$prefix}useful_life_months", 'required');
        }
    }

    public static function basis(int $year, int $net, int $gross): int
    {
        return FiscalYear::vatModeFor($year) === 'small_business' ? $gross : $net;
    }

    /** What an asset made from this expense starts out as. */
    public static function defaults(Expense $expense): array
    {
        $gross = (int) ($expense->gross_eur ?? 0);
        $vat = $expense->gross > 0 ? intdiv($expense->vat * $gross * 2 + $expense->gross, $expense->gross * 2) : 0;
        $date = $expense->invoice_date ?? $expense->paid_at ?? now();
        $net = $gross - $vat;

        return [
            'name' => $expense->description ?: (string) $expense->vendor,
            'acquired_at' => $date->format('Y-m-d'),
            'cost_net' => $net,
            'cost_gross' => $gross,
            'method' => Depreciation::isGwg(self::basis($date->year, $net, $gross)) ? DepreciationMethod::Gwg->value : DepreciationMethod::Linear->value,
            'useful_life_months' => null,
            'afa_category' => null,
        ];
    }

    /** @param  array<string, mixed>|null|false  $payload  false leaves the asset as it is, null removes it */
    public function __invoke(Expense $expense, array|null|false $payload = false, ?User $actor = null): ?FixedAsset
    {
        $asset = $expense->asset;

        if ($payload === null) {
            if ($asset !== null) {
                $this->remove($expense, $asset);
            }

            return null;
        }

        if ($payload === false) {
            $asset?->update(['business_share' => $expense->business_share]);

            return $asset;
        }

        $attributes = [
            ...Arr::only($payload, self::FIELDS),
            'useful_life_months' => self::months($payload),
            'business_share' => $expense->business_share,
        ];

        if ($attributes['method'] === DepreciationMethod::Gwg->value) {
            $attributes['useful_life_months'] = null;
        }

        return DB::transaction(function () use ($expense, $asset, $attributes, $actor) {
            $year = CarbonImmutable::parse($attributes['acquired_at'])->year;

            if ($asset === null || ! str_starts_with($asset->inventory_no, 'INV-'.$this->yy($year).'-')) {
                $attributes['inventory_no'] = $this->allocate($year);
            }

            $created = $asset === null;
            $asset ??= new FixedAsset;
            $asset->fill($attributes)->save();

            if ($created) {
                $expense->asset()->associate($asset)->save();
                AuditLogger::record('finance.asset.created', $actor, ['inventory_no' => $asset->inventory_no], $asset->public_id);
            }

            return $asset;
        });
    }

    private function remove(Expense $expense, FixedAsset $asset): void
    {
        if ($asset->disposed_at !== null) {
            throw ValidationException::withMessages(['asset' => __('finance.assetDisposed')]);
        }

        $asset->delete();
        $expense->setRelation('asset', null);
    }

    private function allocate(int $year): string
    {
        FiscalYear::forYear($year);
        FiscalYear::query()->where('year', $year)->lockForUpdate()->first();

        $yy = $this->yy($year);
        $last = FixedAsset::query()
            ->where('inventory_no', 'like', "INV-$yy-%")
            ->selectRaw("max(substring(inventory_no from '[0-9]+$')::int) as last")
            ->value('last');

        return sprintf('INV-%s-%03d', $yy, ((int) $last) + 1);
    }

    private function yy(int $year): string
    {
        return str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
    }

    /** The typed life, or the one the chosen AfA category stands for. */
    private static function months(array $asset): ?int
    {
        return ! empty($asset['useful_life_months'])
            ? (int) $asset['useful_life_months']
            : AfaCategories::months($asset['afa_category'] ?? null);
    }
}
