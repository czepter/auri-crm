<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

/**
 * #480. The invoice leaves payment_installments and becomes a document of its
 * own: one invoice carries one or more installments of a plan, or — with no
 * project at all — its own items and a single installment outside any plan.
 *
 * Installments keep cancelled_at and the cancelled status: the plan's sum
 * trigger reads them. Everything else about the document moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('number');
            $table->timestampTz('issued_at');

            // Null only once a DSGVO erasure removed the person: § 147 AO keeps
            // the invoice, and its merge-data snapshot keeps the address.
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('template_id')->nullable()->constrained('document_templates');
            $table->string('pdf_path')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('viewed_at')->nullable();

            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_kind')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestampsTz();

            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            // Gross minor units, like PaymentInstallment::$amount — the figure
            // the client pays; InvoiceFigures splits the tax out of it.
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('vat_rate');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::table('payment_installments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_plan_id')->nullable()->change();
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
        });

        $this->moveIssuedInstallments();

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_installment_id');
            $table->foreignId('invoice_id')->nullable(false)->change();
        });

        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_number']);
            $table->dropConstrainedForeignId('invoice_template_id');
            $table->dropColumn([
                'invoice_number', 'invoiced_at', 'invoice_path',
                'sent_at', 'viewed_at', 'cancellation_kind', 'cancellation_reason',
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Not reversible: invoices carrying several installments have no single row to fold back into.');
    }

    private function moveIssuedInstallments(): void
    {
        $rows = DB::table('payment_installments')
            ->join('payment_plans', 'payment_plans.id', '=', 'payment_installments.payment_plan_id')
            ->join('jobs', 'jobs.id', '=', 'payment_plans.job_id')
            ->whereNotNull('payment_installments.invoice_number')
            ->select('payment_installments.*', 'jobs.project_id')
            ->orderBy('payment_installments.id')
            ->get();

        foreach ($rows as $row) {
            $contactId = DB::table('project_contacts')
                ->where('project_id', $row->project_id)
                ->where('role', 'primary')
                ->value('contact_id');

            $invoiceId = DB::table('invoices')->insertGetId([
                'tenant_id' => $row->tenant_id,
                'public_id' => (string) new Cuid2,
                'number' => $row->invoice_number,
                'issued_at' => $row->invoiced_at,
                'contact_id' => $contactId,
                'project_id' => $row->project_id,
                'template_id' => $row->invoice_template_id,
                'pdf_path' => $row->invoice_path,
                'sent_at' => $row->sent_at,
                'viewed_at' => $row->viewed_at,
                'cancelled_at' => $row->cancelled_at,
                'cancellation_kind' => $row->cancellation_kind,
                'cancellation_reason' => $row->cancellation_reason,
                'created_at' => $row->invoiced_at,
                'updated_at' => now(),
            ]);

            DB::table('payment_installments')->where('id', $row->id)->update(['invoice_id' => $invoiceId]);
            DB::table('credit_notes')->where('payment_installment_id', $row->id)->update(['invoice_id' => $invoiceId]);
        }
    }
};
