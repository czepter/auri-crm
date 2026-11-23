<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->string('vendor', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('invoice_no', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->bigInteger('net')->default(0);
            $table->integer('vat_rate')->default(0);
            $table->bigInteger('vat')->default(0);
            $table->bigInteger('gross')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->bigInteger('gross_eur')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            $table->smallInteger('business_share')->default(100);
            $table->foreignId('asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete();
            $table->string('status', 10)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['tenant_id', 'paid_at']);
        });

        DB::statement('alter table expenses add constraint expenses_business_share_check check (business_share between 0 and 100)');
        DB::statement('alter table expenses add constraint expenses_gross_check check (gross = net + vat)');
        DB::statement("alter table expenses add constraint expenses_status_check check (status in ('draft', 'booked', 'ignored'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
