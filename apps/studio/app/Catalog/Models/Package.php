<?php

namespace App\Catalog\Models;

use App\Catalog\Enums\PortalPriceMode;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasHourlyPricing;
use App\Concerns\HasPublicId;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectType;
use Database\Factories\Catalog\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable service definition a quote starts from (Q-02): what the studio
 * offers, named once instead of typed onto every quote from scratch.
 *
 * A package is a starting point, never a live link. Nothing anywhere holds a
 * foreign key from a quote back to this row — copyItems() is the only way
 * data leaves here, and it leaves as a plain array with no id attached. That
 * is what makes editing or deactivating a package afterwards touch nothing
 * already built from it: there is no reference left to drift.
 */
class Package extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasHourlyPricing;
    use HasPublicId;

    protected $fillable = [
        'name', 'description', 'is_active', 'hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours',
        'portal_offered', 'portal_text', 'portal_price_mode', 'portal_requires_date', 'project_type_key',
    ];

    protected $attributes = ['is_active' => true, 'portal_offered' => false, 'portal_requires_date' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'portal_offered' => 'boolean',
            'portal_requires_date' => 'boolean',
            'portal_price_mode' => PortalPriceMode::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order');
    }

    /** Q-11's template shares, copied onto a quote the same dead way copyItems() copies lines. */
    public function paymentStages(): HasMany
    {
        return $this->hasMany(PackagePaymentStage::class)->orderBy('sort_order');
    }

    /**
     * @return list<array{percent: int, label: string, minimum: int, sort_order: int}>
     */
    public function copyPaymentStages(): array
    {
        return $this->paymentStages->map(fn (PackagePaymentStage $stage) => [
            'percent' => $stage->percent,
            'label' => $stage->label,
            'minimum' => $stage->minimum,
            'sort_order' => $stage->sort_order,
        ])->all();
    }

    /** Available to start a new quote from. A deactivated package drops out of this and nothing else. */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Was ein Bestandskunde unter „Weitere Leistungen" sieht. Getrennt von
     * scopeActive(): stillgelegt heißt „nicht mehr als Angebotsvorlage" und
     * soll einem Kunden nicht das Portal umbauen — die Zusage von
     * scopeActive(), dass eine Stilllegung nichts Bestehendes bewegt, gilt
     * auch für das Portal.
     */
    public function scopePortalOffered(Builder $query): void
    {
        $query->where('portal_offered', true);
    }

    /**
     * Die Anfragen aus dem Portal, die von diesem Paket ausgingen. Anders als
     * ein Angebot hält der Lead einen Fremdschlüssel hierher — er kopiert
     * aber nichts von hier, sodass eine Änderung am Paket ihn weiterhin nicht
     * bewegt.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** Ohne Projekttyp hat eine Portalanfrage keinen Shoot-Kind und keine Felder. */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_key', 'key');
    }

    /**
     * A deep, disconnected copy of this package's items — Q-02's seed step.
     * No id and no package_id in the result, on purpose: carrying either would
     * be the live reference back to this row that a package must never hold,
     * since it is what would let editing or deactivating the package here
     * move a line a quote already copied out of it.
     *
     * Q-09/Q-10 ride along: `selectable`, `selected`, `outcome` and the
     * copied `image_path`. The last one is the point of copying rather than
     * referencing — swap the catalogue photo later and a quote already sent
     * keeps the picture it was sent with.
     *
     * @return list<array{title: string, description: ?string, quantity: int, unit_price: ?int, selectable: bool, selected: bool, outcome: ?string, image_path: ?string}>
     */
    public function copyItems(): array
    {
        return $this->items->map(fn (PackageItem $item) => [
            'title' => $item->title,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'selectable' => $item->selectable,
            'selected' => $item->selected,
            'outcome' => $item->outcome,
            'image_path' => $item->image_path,
        ])->all();
    }

    protected static function newFactory(): Factory
    {
        return PackageFactory::new();
    }
}
