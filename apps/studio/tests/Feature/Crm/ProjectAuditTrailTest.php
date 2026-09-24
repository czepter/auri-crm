<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('files a shoot status change under its project', function () {
    $shoot = Shoot::factory()->create();

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Booked), actor: staffMember());

    expect(AuditLog::where('event', 'shoot.status_changed')->sole()->case_id)
        ->toBe($shoot->case_id);
});

it('files a sent quote under its project', function () {
    $quote = Quote::factory()->create();

    app(SendQuote::class)($quote);

    expect(AuditLog::where('event', 'quote.sent')->sole()->case_id)
        ->toBe($quote->case_id);
});

it('files a paid installment under the project three relations away', function () {
    $installment = PaymentInstallment::factory()->create();

    app(MarkInstallmentPaid::class)(
        $installment, SettledVia::Transfer, '2026-06-21', null, staffMember()
    );

    expect(AuditLog::where('event', 'payment_installment.paid')->sole()->case_id)
        ->toBe($installment->plan->case_id);
});
