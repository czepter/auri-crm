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
            $table->string('vat_mode')->default('standard');

            // Basis points (0-10000), never a float — the same "no float
            // touches money" rule App\Quotes\Enums\DiscountType documents
            // for discounts applies here too, since Q-04 will divide this
            // straight into a tax amount. 1900 is 19.00%.
            $table->unsignedInteger('vat_rate')->default(1900);

            // Editable, not a constant: the exact wording is the studio's to
            // phrase, per the issue. Seeded with the standard §19 UStG
            // Kleinunternehmer notice so a studio that switches to
            // small_business already has correct legal wording rather than
            // an empty box to fill in from scratch.
            $table->text('vat_note')->nullable()->default('Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');
        });

        // Postgres has no native enum-by-column-type here (matching T-01's
        // display_name being a plain string, not a Postgres enum type), so
        // this check constraint is what actually makes a third mode
        // unrepresentable, independent of the controller's own validation —
        // the same belt-and-suspenders quotes_net_check already uses.
        DB::statement("alter table studio_settings add constraint studio_settings_vat_mode_check check (vat_mode in ('standard', 'small_business'))");
        DB::statement('alter table studio_settings add constraint studio_settings_vat_rate_check check (vat_rate >= 0 and vat_rate <= 10000)');
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['vat_mode', 'vat_rate', 'vat_note']);
        });
    }
};
