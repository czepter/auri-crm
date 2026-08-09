<?php

namespace App\Contracts\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use Database\Factories\Contracts\ContractSignatureFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * The evidence, not a flag: a typed name plus explicit consent plus the
 * SHA-256 of the exact bytes Storage served for one Contract, at one moment,
 * from one IP and browser, against one named DocumentTemplate version.
 *
 * Append-only in the same shape as App\Models\AuditLog — signing is one
 * transaction (see App\Contracts\Actions\SignContract) and there is no
 * second one that unsigns. A future "void" story stands beside this row,
 * never through it.
 */
class ContractSignature extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'contract_id', 'contact_id', 'typed_name', 'consented_at',
        'document_sha256', 'contract_template_version', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Contract signatures are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Contract signatures are append-only.'));
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    protected static function newFactory(): Factory
    {
        return ContractSignatureFactory::new();
    }
}
