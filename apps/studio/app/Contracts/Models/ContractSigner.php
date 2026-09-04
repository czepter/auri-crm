<?php

namespace App\Contracts\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One contact expected to sign one Fassung. The evidence is ContractSignature. */
class ContractSigner extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['contract_id', 'contact_id', 'signed_at', 'reminded_at'];

    protected function casts(): array
    {
        return [
            'signed_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
