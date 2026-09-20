<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #409. Its own table, unlike the invoice — which lives as four columns on
 * payment_installments because one installment is one invoice. A paid
 * installment can carry several Gutschriften (a partial one is the normal
 * case), so there is nowhere on the row to put them.
 *
 * `number` is unique per tenant for the reason invoice_number is: the range
 * is per tenant (T-04), so two studios both issuing GS-2026-1 is correct and
 * the same studio doing it twice must be impossible.
 *
 * `template_id` pins the wording at issue, the same way invoice_template_id
 * and contracts do — editing the Gutschrift template later must not change a
 * document a client already holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('payment_installment_id')->constrained()->cascadeOnDelete();

            $table->string('number');
            $table->timestampTz('issued_at');

            // Minor units, like PaymentInstallment::$amount. At most the
            // installment's amount less every earlier credit note — a rule
            // IssueCreditNote enforces, because it needs to read siblings.
            $table->unsignedInteger('amount');

            $table->text('reason');
            $table->string('pdf_path')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('document_templates');

            // The refund this Gutschrift triggered, where the installment was
            // settled through Stripe. Null for a transfer: paying that back is
            // the studio's own bank transfer and this document only records it.
            $table->string('stripe_refund_id')->nullable();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'number']);
            $table->index('payment_installment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
