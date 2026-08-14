<?php

namespace App\Documents\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Documents\Enums\DocumentTemplateType;
use Database\Factories\Documents\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Visus\Cuid2\Cuid2;

/**
 * T-12's generalisation of M-01's ContractTemplate: one versioned template
 * model for every document type (contract, quote, mail), rather than three
 * parallel systems each with their own idea of what a merge field means.
 *
 * Everything M-01 and M-02 already relied on is unchanged in shape:
 * `family_id` is stable across every edit, each row's own `public_id` names
 * one exact version, and editing never mutates a row — newVersion() inserts
 * a new one and moves `is_current`, so a Contract already pointing at a row
 * here (still via `contracts.contract_template_id`, see the migration that
 * moved this table's data) keeps pointing at exactly that wording. See
 * docs/documents.md for the merge-field vocabulary and the letterhead/footer
 * every Contract/Quote render wraps a template's body in.
 *
 * `type` and `shoot_kind` name what a family *is* and never change across
 * its own versions — newVersion() carries both forward from the row it is
 * called on rather than accepting them as edit input.
 */
class DocumentTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $table = 'document_templates';

    protected $fillable = ['family_id', 'version', 'type', 'shoot_kind', 'name', 'body', 'blocks', 'is_current'];

    protected $attributes = ['version' => 1, 'is_current' => true];

    protected function casts(): array
    {
        return [
            'type' => DocumentTemplateType::class,
            'blocks' => 'array',
            'is_current' => 'boolean',
        ];
    }

    /** The version every read that only cares about "the template" starts from. */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function scopeOfType(Builder $query, DocumentTemplateType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Every generic (no shoot kind) template, plus whichever variant matches
     * $shootKind — T-12's "variants per shoot kind where needed" rather than
     * one template full of conditionals. Passing null returns only the
     * generic rows, since there is no project to vary against.
     */
    public function scopeForShootKind(Builder $query, ?string $shootKind): void
    {
        $query->where(function (Builder $query) use ($shootKind) {
            $query->whereNull('shoot_kind');

            if ($shootKind !== null) {
                $query->orWhere('shoot_kind', $shootKind);
            }
        });
    }

    /**
     * Inserts a new version in this template's family and moves the current
     * pointer to it. The old row is left untouched — no update, no delete —
     * so a Contract already rendered from it, and the PDF that render wrote
     * to storage, are never touched by this call.
     *
     * type and shoot_kind are never accepted here: they identify the family,
     * not one version of its wording, and stay whatever $this already has.
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
                'type' => $this->type,
                'shoot_kind' => $this->shoot_kind,
                'name' => $attributes['name'],
                'body' => $attributes['body'],
                'blocks' => $attributes['blocks'] ?? $this->blocks,
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
            'type' => $attributes['type'],
            'shoot_kind' => $attributes['shoot_kind'] ?? null,
            'name' => $attributes['name'],
            'body' => $attributes['body'],
            'blocks' => $attributes['blocks'] ?? null,
            'is_current' => true,
        ]);
    }

    protected static function newFactory(): Factory
    {
        return DocumentTemplateFactory::new();
    }
}
