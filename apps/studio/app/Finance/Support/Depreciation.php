<?php

namespace App\Finance\Support;

use App\Finance\Enums\DepreciationMethod;
use App\Finance\Models\FixedAsset;
use Carbon\CarbonInterface;

/**
 * Linear AfA by cumulative months, so rounding never drifts: the amount
 * written off up to a month is `half-up(basis * months / life)` and a year's
 * share is the difference of two cumulative figures. The last year therefore
 * takes whatever remainder the earlier ones left. A GWG is a one-month life.
 *
 * Months count from the acquisition month inclusive; a disposal stops the
 * count at its own month, also inclusive. Figures are at 100 %; the business
 * share is applied by whoever reports them.
 */
final class Depreciation
{
    /** Anschaffungskosten up to which a purchase may be written off at once, in cents. */
    public const GWG_LIMIT = 80000;

    public function __construct(
        public readonly DepreciationMethod $method,
        public readonly int $basis,
        public readonly CarbonInterface $acquiredAt,
        public readonly ?int $lifeMonths,
        public readonly ?CarbonInterface $disposedAt = null,
    ) {}

    public static function of(FixedAsset $asset): self
    {
        return new self($asset->method, $asset->basis(), $asset->acquired_at, $asset->useful_life_months, $asset->disposed_at);
    }

    public static function isGwg(int $basis): bool
    {
        return $basis <= self::GWG_LIMIT;
    }

    /** Half-up share of a cent amount, for a business share of 0 to 100. */
    public static function share(int $amount, int $percent): int
    {
        return intdiv($amount * $percent * 2 + 100, 200);
    }

    public function yearly(int $year): int
    {
        return $this->written($year, 12) - $this->written($year - 1, 12);
    }

    /** Value still carried at the end of that month, 0 once disposed. */
    public function bookValue(CarbonInterface $asOf): int
    {
        if ($this->disposedAt !== null && $this->monthIndex($asOf->year, $asOf->month) >= $this->monthIndex($this->disposedAt->year, $this->disposedAt->month)) {
            return 0;
        }

        return $this->basis - $this->written($asOf->year, $asOf->month);
    }

    /** What is left to write off in the disposal month, taken as an expense. */
    public function residualAtDisposal(): int
    {
        return $this->disposedAt === null ? 0 : $this->basis - $this->written($this->disposedAt->year, $this->disposedAt->month);
    }

    private function written(int $year, int $month): int
    {
        $months = $this->monthIndex($year, $month) - $this->monthIndex($this->acquiredAt->year, $this->acquiredAt->month) + 1;

        if ($this->disposedAt !== null) {
            $months = min($months, $this->monthIndex($this->disposedAt->year, $this->disposedAt->month) - $this->monthIndex($this->acquiredAt->year, $this->acquiredAt->month) + 1);
        }

        $life = $this->method === DepreciationMethod::Gwg ? 1 : (int) $this->lifeMonths;

        return intdiv($this->basis * min(max($months, 0), $life) * 2 + $life, $life * 2);
    }

    private function monthIndex(int $year, int $month): int
    {
        return $year * 12 + $month - 1;
    }
}
