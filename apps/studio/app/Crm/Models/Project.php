<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use Database\Factories\Crm\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Visus\Cuid2\Cuid2;

/**
 * The relationship, from inquiry to archive. Owns the contacts, the
 * conversation and the shoots.
 *
 * Carries no pipeline status on purpose: a project whose engagement shoot is
 * delivered while the wedding day is unshot has no single state, and inventing
 * one produces a status that is wrong for most of a wedding's eighteen-month
 * life (vision §3.1). Board position is derived.
 */
class Project extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'type', 'portal_visible'];

    /**
     * Mirrors the column default in memory. Without it a freshly created
     * Project reports portal_visible as null until it is reloaded, and a
     * visibility flag that reads null where it means false is the kind of
     * thing a guard clause gets wrong exactly once.
     */
    protected $attributes = ['portal_visible' => false];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'portal_visible' => 'boolean',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'project_contacts')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * attach() writes through the query builder, which bypasses the model
     * events BelongsToTenant and HasPublicId hook into. This is the one place
     * in the context where the seam columns are filled by hand.
     */
    public function attachContact(Contact $contact, ProjectContactRole $role): void
    {
        $this->contacts()->attach($contact, [
            'role' => $role->value,
            'tenant_id' => config('tenancy.tenant_id'),
            'public_id' => (string) new Cuid2,
        ]);
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    public function shoots(): HasMany
    {
        return $this->hasMany(Shoot::class)->orderBy('sort_order');
    }

    /** The headline occasion — the wedding day, not the engagement shoot. */
    public function primaryShoot(): HasOne
    {
        return $this->hasOne(Shoot::class)->where('is_primary', true);
    }

    public function primaryContact(): ?Contact
    {
        return $this->contacts()
            ->wherePivot('role', ProjectContactRole::Primary->value)
            ->first();
    }

    protected static function newFactory(): Factory
    {
        return ProjectFactory::new();
    }
}
