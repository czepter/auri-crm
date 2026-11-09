<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('view_mode')->default('none');
            $table->string('watermark_text')->nullable();
            $table->string('download_rule')->default('open');
            $table->foreignId('download_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->jsonb('download_sizes')->default('["original","web"]');
            $table->boolean('expiry_reminder')->default(false);
            $table->timestampTz('expiry_reminded_at')->nullable();
        });

        DB::statement("alter table galleries add constraint galleries_view_mode_check check (view_mode in ('none', 'text', 'logo', 'locked'))");
        DB::statement("alter table galleries add constraint galleries_download_rule_check check (download_rule in ('open', 'after_payment', 'off'))");
        DB::table('galleries')->where('downloads_enabled', false)->update(['download_rule' => 'off']);
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('download_invoice_id');
            $table->dropColumn(['view_mode', 'watermark_text', 'download_rule', 'download_sizes', 'expiry_reminder', 'expiry_reminded_at']);
        });
    }
};
