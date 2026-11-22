<?php

namespace App\Finance\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Models\StudioSettings;
use App\Models\User;
use Database\Factories\Finance\FiscalYearFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row per year the studio has touched, created lazily. `vat_mode` is the
 * year's own setting because the status can change from one year to the next;
 * a locked year keeps the snapshot of its report and the PDF rendered from it.
 */
class FiscalYear extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['year', 'vat_mode', 'locked_at', 'locked_by', 'snapshot', 'pdf_path'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'locked_at' => 'immutable_datetime',
            'snapshot' => 'array',
        ];
    }

    public static function forYear(int $year): self
    {
        return static::query()->firstOrCreate(
            ['year' => $year],
            ['vat_mode' => StudioSettings::current()->vat_mode],
        );
    }

    /** Reading a year's mode must not create its row. */
    public static function vatModeFor(int $year): string
    {
        return static::query()->where('year', $year)->value('vat_mode') ?? StudioSettings::current()->vat_mode;
    }

    /**
     * Every year with data, plus the current one, newest first.
     *
     * @return list<int>
     */
    public static function known(): array
    {
        return collect([
            now()->year,
            ...static::query()->pluck('year'),
            ...Expense::query()->selectRaw('distinct extract(year from coalesce(paid_at, invoice_date, created_at::date))::int as year')->pluck('year'),
            ...FixedAsset::query()->selectRaw('distinct extract(year from acquired_at)::int as year')->pluck('year'),
        ])->map(fn ($year) => (int) $year)->unique()->sortDesc()->values()->all();
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    protected static function newFactory(): Factory
    {
        return FiscalYearFactory::new();
    }
}
