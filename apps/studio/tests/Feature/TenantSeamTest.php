<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('seam_probes', function (Blueprint $table) {
        $table->id();
        $table->tenantId();
        $table->publicId();
        $table->string('label');
    });
});

afterEach(fn () => Schema::dropIfExists('seam_probes'));

it('fills the tenant id on create', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->fresh()->tenant_id)->toBe(config('tenancy.tenant_id'));
});

it('hides rows belonging to another tenant', function () {
    TenantProbe::create(['label' => 'ours']);

    DB::table('seam_probes')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => 'aaaaaaaaaaaaaaaaaaaaaaaa',
        'label' => 'theirs',
    ]);

    expect(TenantProbe::pluck('label')->all())->toBe(['ours']);
});

class TenantProbe extends \Illuminate\Database\Eloquent\Model
{
    use \App\Concerns\BelongsToTenant;
    use \App\Concerns\HasPublicId;

    protected $table = 'seam_probes';

    public $timestamps = false;

    protected $fillable = ['label'];
}
