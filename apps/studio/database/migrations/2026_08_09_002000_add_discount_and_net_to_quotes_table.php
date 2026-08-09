<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('discount_type')->nullable()->after('total');
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->unsignedInteger('discount_amount')->default(0)->after('discount_value');

            // total stays Q-02's gross sum, untouched. net is what Q-03
            // adds: total's already-line-discounted lines, less this
            // quote's own discount, rounded once by
            // DiscountCalculator::resolve() and never again — see
            // Quote::recalculateTotal().
            $table->unsignedInteger('net')->default(0)->after('discount_amount');
        });

        // unsignedInteger is nominal on Postgres — it has no native unsigned
        // integer type, so column type alone enforces nothing. This check
        // constraint is what actually makes a negative net unrepresentable,
        // independent of DiscountCalculator's own capping.
        DB::statement('alter table quotes add constraint quotes_net_check check (net >= 0)');
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount', 'net']);
        });
    }
};
