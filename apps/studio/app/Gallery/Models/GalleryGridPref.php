<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GalleryGridPref extends Model
{
    use BelongsToTenant;

    public const DEFAULT_DENSITY = 'medium';

    protected $fillable = ['user_id', 'gallery_set_id', 'density'];
}
