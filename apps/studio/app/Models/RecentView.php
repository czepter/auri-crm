<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * C-03 (#237): what this user opened, newest first. The command palette's
 * whole content until search over the stock arrives.
 *
 * No public id: nothing ever addresses one of these rows. They are read as a
 * list and written by their natural key — see the migration for why that key
 * is (user, kind, subject) and why the title is copied rather than joined.
 */
class RecentView extends Model
{
    use BelongsToTenant;

    public const KEPT = 20;

    public $timestamps = false;

    protected $fillable = ['user_id', 'kind', 'subject_id', 'title', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
