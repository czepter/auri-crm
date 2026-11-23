<?php

namespace App\Finance\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['key', 'label', 'euer_line', 'skr04_account', 'sort_order'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}
