<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('inventory_no', 20);
            $table->string('name', 200);
            $table->date('acquired_at');
            $table->bigInteger('cost_net');
            $table->bigInteger('cost_gross');
            $table->string('method', 10);
            $table->smallInteger('useful_life_months')->nullable();
            $table->string('afa_category', 50)->nullable();
            $table->smallInteger('business_share')->default(100);
            $table->date('disposed_at')->nullable();
            $table->bigInteger('disposal_proceeds')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'inventory_no']);
        });

        DB::statement("alter table fixed_assets add constraint fixed_assets_method_check check (method in ('gwg', 'linear'))");
        DB::statement('alter table fixed_assets add constraint fixed_assets_business_share_check check (business_share between 0 and 100)');
        DB::statement("alter table fixed_assets add constraint fixed_assets_useful_life_check check (method <> 'linear' or useful_life_months > 0)");
        DB::statement('alter table fixed_assets add constraint fixed_assets_disposal_proceeds_check check (disposal_proceeds is null or disposal_proceeds >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
