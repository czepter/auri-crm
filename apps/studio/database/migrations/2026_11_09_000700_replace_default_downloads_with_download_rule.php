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
            $table->string('gallery_default_download_rule')->default('open');
        });

        DB::statement("alter table studio_settings add constraint studio_settings_gallery_default_download_rule_check check (gallery_default_download_rule in ('open', 'after_payment', 'off'))");
        DB::table('studio_settings')->where('gallery_default_downloads_enabled', false)->update(['gallery_default_download_rule' => 'off']);

        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('gallery_default_downloads_enabled'));
    }

    public function down(): void
    {
        Schema::table('studio_settings', fn (Blueprint $table) => $table->boolean('gallery_default_downloads_enabled')->default(true));

        DB::table('studio_settings')->where('gallery_default_download_rule', 'off')->update(['gallery_default_downloads_enabled' => false]);

        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn('gallery_default_download_rule'));
    }
};
