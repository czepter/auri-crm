<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            // Percentage is basis points (0–10000); amount is the same
            // minor-unit as unit_price. discount_amount is the one Q-03's
            // total actually reads — resolved and stored the moment
            // quantity/unit_price/discount_type/discount_value are saved
            // (QuoteItem::booted(), via App\Quotes\Support\DiscountCalculator),
            // never recomputed from type and value anywhere else.
            $table->string('discount_type')->nullable()->after('unit_price');
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->unsignedInteger('discount_amount')->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
