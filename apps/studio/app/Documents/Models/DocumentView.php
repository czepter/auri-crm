<?php

namespace App\Documents\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Payments\Models\Invoice;
use App\Quotes\Models\Quote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentView extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public $timestamps = false;

    protected $fillable = ['quote_id', 'invoice_id', 'via', 'ip_hash', 'user_agent', 'counts', 'viewed_at'];

    protected function casts(): array
    {
        return ['counts' => 'boolean', 'viewed_at' => 'immutable_datetime'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
