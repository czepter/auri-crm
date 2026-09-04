<?php

namespace Tests\Feature;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Exercises the two concerns against a table no migration owns, so
 * TenantSeamTest and PublicIdTest read the seam itself rather than whatever
 * a domain model happens to do with it. Its own file because a class declared
 * at the foot of one test file only exists once Pest has loaded that file,
 * and Pest loads PublicIdTest first.
 */
class TenantProbe extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'seam_probes';

    public $timestamps = false;

    protected $fillable = ['label'];
}
