<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q-11: the template in front of the plan.
     *
     * Not PaymentPlan itself: that one hangs off a Job, its `total` is
     * authoritative, and a deferred constraint trigger rejects any commit
     * whose installments do not add up to it. None of that changes — a
     * quote with a slider simply has no fixed sum yet, so what it carries is
     * shares, and CreatePaymentPlan materialises installments from them at
     * booking.
     *
     * No anchor enum. ShootKind knows Verlobungsshooting, Hochzeitstag,
     * Feier, Portrait and Sonstige; a fixed list of payment moments would
     * force the wrong word on half of them.
     */
    public function up(): void
    {
        foreach (['quote_payment_stages' => 'quote_id', 'package_payment_stages' => 'package_id'] as $table => $owner) {
            Schema::create($table, function (Blueprint $blueprint) use ($owner) {
                $blueprint->id();
                $blueprint->tenantId();
                $blueprint->publicId();
                $blueprint->foreignId($owner)->constrained()->cascadeOnDelete();
                $blueprint->unsignedSmallInteger('percent');
                $blueprint->string('label');
                // Minor units, same convention as every amount in this app.
                // Zero means no floor, which is why it is not nullable.
                $blueprint->integer('minimum')->default(0);
                $blueprint->integer('sort_order')->default(0);
                $blueprint->timestampsTz();
            });
        }

        Schema::table('payment_installments', function (Blueprint $blueprint) {
            // Q-11: the stage's own free text, carried onto the installment.
            // PaymentPlanData::fromModel() derives "Anzahlung / 2. Rate /
            // Restbetrag" from sort_order, and that derivation cannot carry a
            // sentence somebody wrote.
            $blueprint->string('title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $blueprint) {
            $blueprint->dropColumn('title');
        });

        Schema::dropIfExists('quote_payment_stages');
        Schema::dropIfExists('package_payment_stages');
    }
};
