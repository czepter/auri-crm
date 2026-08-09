<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            // T-05's four document defaults — read once, at the moment a new
            // quote/payment plan/contract render is created, and never
            // re-read afterwards. Changing any of these moves nothing already
            // built.

            // Vision §3 puts an equivalent column on Package for a quote
            // seeded from one — Package carries no such column today (see
            // App\Catalog\Models\Package), so this is the only fallback a
            // quote without an explicit valid_until has, package or not
            // (App\Quotes\Http\Controllers\QuoteController::store).
            $table->unsignedInteger('quote_validity_days')->default(14);

            // Zahlungsziel — T-07's overdue scan reads this once it exists;
            // this story only stores the value.
            $table->unsignedInteger('payment_term_days')->default(14);

            // Basis points (0-10000), same "no float touches money" rule
            // vat_rate above already documents. Pre-fills a new
            // App\Payments\Models\PaymentPlan's deposit installment.
            $table->unsignedInteger('deposit_percentage')->default(3000);

            // Whether a Fernabsatzvertrag's Widerrufsbelehrung is on by
            // default. True: the legally safer default under §312g BGB — an
            // included notice that turns out to be unnecessary costs
            // nothing, an omitted one that turns out to be required is a
            // consumer-law violation. The studio can switch it off per its
            // own reading of the §312g Abs. 2 Nr. 9 exemption.
            $table->boolean('widerruf_included')->default(true);

            $table->text('widerruf_text')->nullable()->default(
                'Widerrufsrecht: Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen '.
                'Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses.'
            );
        });

        DB::statement('alter table studio_settings add constraint studio_settings_deposit_percentage_check check (deposit_percentage >= 0 and deposit_percentage <= 10000)');
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['quote_validity_days', 'payment_term_days', 'deposit_percentage', 'widerruf_included', 'widerruf_text']);
        });
    }
};
