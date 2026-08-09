<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-04: the Stripe object this story actually creates. A Checkout Session,
 * not an invoice — see App\Payments\Actions\CreateInstallmentCheckoutSession
 * for why. Null until the client's own "Jetzt zahlen" click creates one
 * (T-07's "nothing is created in Stripe until the client clicks pay"); once
 * set, a repeat click reuses this id rather than creating a second session.
 * Unique so two installments can never end up pointing at the same session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn('stripe_checkout_session_id');
        });
    }
};
