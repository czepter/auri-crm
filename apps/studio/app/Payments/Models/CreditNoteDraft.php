<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The Gutschrift form as it stood, one per invoice; no number is drawn until it is issued. */
class CreditNoteDraft extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['invoice_id', 'created_by', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
