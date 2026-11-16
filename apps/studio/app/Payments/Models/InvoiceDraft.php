<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The create form as it was posted, kept apart from Invoice: an invoice draws
 * its number when issued and is immutable from then on, a draft is neither.
 */
class InvoiceDraft extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['case_id', 'contact_id', 'created_by', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class, 'case_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
