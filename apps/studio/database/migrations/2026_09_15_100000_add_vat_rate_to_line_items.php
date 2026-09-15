<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #412: § 14 Abs. 4 Nr. 7 UStG wants the Entgelt "nach Steuersätzen
 * aufgeschlüsselt", so a line can carry its own rate — basis points, the
 * convention DiscountCalculator and StudioSettingsResolver::vatRate() share.
 *
 * Null is not "no tax": it means the quote's own rate, which is where every
 * existing line already gets its rate from. The catalog carries the column
 * too, so a package brings the 7 % photo book with it instead of the studio
 * setting the rate again on every quote seeded from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quote_items', 'package_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('vat_rate')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quote_items', 'package_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('vat_rate');
            });
        }
    }
};
