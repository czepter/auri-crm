<?php

namespace App\Finance\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Finance\FinanceDocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A receipt file. Named for finance because App\Documents already means
 * templates and merge fields. The bytes sit on the `s3` disk under a key the
 * server generates; the client's filename is data only.
 */
class FinanceDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'filename', 'mime', 'size', 'sha256', 'source_ref', 'is_self_issued', 'uploaded_at', 'created_by',
    ];

    protected $attributes = ['is_self_issued' => false];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'source_ref' => 'array',
            'is_self_issued' => 'boolean',
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    public function storageKey(): string
    {
        return "finance/{$this->tenant_id}/{$this->public_id}";
    }

    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'expense_documents', 'document_id', 'expense_id');
    }

    protected static function newFactory(): Factory
    {
        return FinanceDocumentFactory::new();
    }
}
