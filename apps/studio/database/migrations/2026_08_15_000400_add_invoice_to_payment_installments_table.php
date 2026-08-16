<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-06: issuing an invoice stamps the installment. There is no `Invoice`
 * model, for the reason M-04 already gave for one Stripe invoice per
 * installment — a single late payment should be a single overdue invoice
 * rather than an ambiguous partial state — and a parallel row would only
 * duplicate the amount, the due date and the plan this table already holds.
 *
 * `invoice_number` is unique per tenant rather than globally: the number
 * range is per tenant (T-04), so two studios both issuing RE-2026-1 is
 * correct, and the same studio doing it twice is the thing that must be
 * impossible. The index is what makes that true of the row even for a write
 * that never went through IssueInvoice.
 *
 * `invoice_template_id` pins the wording, the same way contracts pin theirs:
 * editing an invoice template later must not change what a client already
 * received. It is nullable only because a render can be re-run for an
 * installment issued before any template existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->string('invoice_number')->nullable();
            $table->timestampTz('invoiced_at')->nullable();
            $table->string('invoice_path')->nullable();
            $table->foreignId('invoice_template_id')->nullable()->constrained('document_templates');

            $table->unique(['tenant_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_number']);
            $table->dropConstrainedForeignId('invoice_template_id');
            $table->dropColumn(['invoice_number', 'invoiced_at', 'invoice_path']);
        });
    }
};
