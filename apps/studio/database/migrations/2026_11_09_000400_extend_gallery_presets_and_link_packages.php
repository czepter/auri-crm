<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_presets', function (Blueprint $table) {
            $table->jsonb('sets')->nullable();
            $table->string('view_mode')->nullable();
            $table->string('download_rule')->nullable();
            $table->jsonb('download_sizes')->nullable();
            $table->jsonb('permissions')->nullable();
            $table->boolean('intro_enabled')->nullable();
            $table->boolean('expiry_reminder')->nullable();
            $table->char('mail_template_family_id', 24)->nullable();
        });

        DB::statement("alter table gallery_presets add constraint gallery_presets_view_mode_check check (view_mode in ('none', 'text', 'logo', 'locked'))");
        DB::statement("alter table gallery_presets add constraint gallery_presets_download_rule_check check (download_rule in ('open', 'after_payment', 'off'))");

        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('gallery_preset_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('gallery_preset_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $table) => $table->dropConstrainedForeignId('gallery_preset_id'));
        Schema::table('galleries', fn (Blueprint $table) => $table->dropConstrainedForeignId('gallery_preset_id'));
        Schema::table('gallery_presets', fn (Blueprint $table) => $table->dropColumn([
            'sets', 'view_mode', 'download_rule', 'download_sizes', 'permissions', 'intro_enabled', 'expiry_reminder', 'mail_template_family_id',
        ]));
    }
};
