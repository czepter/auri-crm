<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #480. Invoice positions are written with the quote's position builder, so
 * they take a quote line's shape: title and description, a net unit price,
 * line and invoice discount, a rate that falls back to the studio's. A line
 * picked from a payment plan names its installment; its gross is that
 * installment's amount, to the cent.
 *
 * The few positions stored so far were gross with the description as the
 * only text; they become net at their own rate, the description their title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('title')->default('');
            $table->text('description')->nullable()->change();
            $table->string('discount_type')->nullable();
            $table->unsignedInteger('discount_value')->nullable();
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('vat_rate')->nullable()->change();
            $table->foreignId('payment_installment_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('discount_type')->nullable();
            $table->unsignedInteger('discount_value')->nullable();
        });

        foreach (DB::table('invoice_items')->get() as $item) {
            DB::table('invoice_items')->where('id', $item->id)->update([
                'title' => (string) $item->description,
                'description' => null,
                'unit_price' => intdiv((int) $item->unit_price * 10_000 + intdiv(10_000 + (int) $item->vat_rate, 2), 10_000 + (int) $item->vat_rate),
            ]);
        }
    }

    public function down(): void {}
};
