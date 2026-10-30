<?php

use App\Enums\StaffRole;
use App\Payments\Actions\IssueInvoice;
use App\Quotes\Models\Quote;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('flips counts for a staff member who manages the quote', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();
    $view = $quote->views()->create(['via' => 'link', 'viewed_at' => now()]);

    $this->actingAs($user, 'staff')->patch("/oeffnungen/{$view->public_id}", ['counts' => false])->assertRedirect();

    expect($view->fresh()->counts)->toBeFalse();
});

it('refuses a staff member without quote rights and hides another tenant\'s view', function () {
    $quote = Quote::factory()->for(studioWithProject()[1], 'case')->create();
    $view = $quote->views()->create(['via' => 'link', 'viewed_at' => now()]);

    $this->actingAs(staffMember(StaffRole::Reader), 'staff')
        ->patch("/oeffnungen/{$view->public_id}", ['counts' => false])->assertForbidden();

    DB::table('document_views')->where('id', $view->id)->update(['tenant_id' => '00000000-0000-4000-8000-00000000dead']);

    $this->actingAs(staffMember(), 'staff')
        ->patch("/oeffnungen/{$view->public_id}", ['counts' => false])->assertNotFound();
});

it('dates an invoice by its earliest counting view', function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    Storage::fake('local');
    Bus::fake();

    $invoice = app(IssueInvoice::class)(invoiceablePlan()->plan->installments()->orderBy('sort_order')->get());
    $invoice->views()->create(['via' => 'portal', 'viewed_at' => '2026-10-01 10:00:00', 'counts' => false]);
    $invoice->views()->create(['via' => 'portal', 'viewed_at' => '2026-10-03 10:00:00']);
    $invoice->views()->create(['via' => 'portal', 'viewed_at' => '2026-10-05 10:00:00']);

    $row = $this->actingAs(staffMember(), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['invoices'][0];

    expect($row['viewedOn'])->toBe('2026-10-03')
        ->and($row['views'])->toHaveCount(3);
});
