<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Visus\Cuid2\Cuid2;

/**
 * Splits internal and external identity. The bigint primary key stays inside
 * the database — it carries foreign keys and joins. The CUID2 in public_id is
 * the only identifier that leaves the server, so sequential integers are never
 * enumerable by a client or guest.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model) {
            $model->public_id ??= (string) new Cuid2;
        });
    }

    public function initializeHasPublicId(): void
    {
        $this->hidden = array_values(array_unique([...$this->hidden, 'id']));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function findByPublicId(string $publicId): ?static
    {
        return static::query()->where('public_id', $publicId)->first();
    }
}
