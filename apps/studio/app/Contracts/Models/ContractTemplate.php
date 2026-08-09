<?php

namespace App\Contracts\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Contracts\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

/**
 * A contract's wording, versioned. `family_id` is stable across every edit —
 * it is what makes "the wedding contract" one thing over time — while each
 * row's own `public_id` names one exact version, because M-02 records which
 * version a signature was made against and that has to survive the template
 * being edited again afterwards.
 *
 * Editing never mutates a row here. newVersion() inserts a new one and moves
 * `is_current`; the old row stays exactly as it was, still queryable by
 * anything already pointing at it — a Contract's contract_template_id chief
 * among them. See docs/contracts.md for the `{{ project.name }}` merge-field
 * syntax this model's `body` carries.
 */
class ContractTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['family_id', 'version', 'name', 'body', 'is_current'];

    protected $attributes = ['version' => 1, 'is_current' => true];

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    /** The version every read that only cares about "the template" starts from. */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    /**
     * Inserts a new version in this template's family and moves the current
     * pointer to it. The old row is left untouched — no update, no delete —
     * so a Contract already rendered from it, and the PDF that render wrote
     * to storage, are never touched by this call.
     *
     * The transaction only wraps the read-then-insert-then-flip, the same
     * shape AllocateQuoteNumber uses for its own monotonic counter: two
     * concurrent edits either serialize on it or one loses the unique
     * (tenant_id, family_id, version) index, never both landing "version 3".
     */
    public function newVersion(array $attributes): self
    {
        return DB::transaction(function () use ($attributes) {
            $next = 1 + (int) static::query()
                ->where('tenant_id', $this->tenant_id)
                ->where('family_id', $this->family_id)
                ->max('version');

            $version = static::create([
                'family_id' => $this->family_id,
                'version' => $next,
                'name' => $attributes['name'],
                'body' => $attributes['body'],
                'is_current' => true,
            ]);

            static::query()
                ->where('tenant_id', $this->tenant_id)
                ->where('family_id', $this->family_id)
                ->where('id', '!=', $version->id)
                ->update(['is_current' => false]);

            return $version;
        });
    }

    /** A brand new template, the first version of a fresh family. */
    public static function createFirstVersion(array $attributes): self
    {
        return static::create([
            'family_id' => (string) new Cuid2,
            'version' => 1,
            'name' => $attributes['name'],
            'body' => $attributes['body'],
            'is_current' => true,
        ]);
    }

    protected static function newFactory(): Factory
    {
        return ContractTemplateFactory::new();
    }
}
