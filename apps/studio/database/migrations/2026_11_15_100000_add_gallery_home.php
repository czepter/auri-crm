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
            $table->boolean('listed_on_home')->default(false);
            $table->boolean('home_pinned')->default(false);
            $table->foreignId('project_type_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'listed_on_home', 'home_pinned']);
        });

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('gallery_home_mode', 16)->default('none');
            $table->string('gallery_home_redirect_url', 2000)->nullable();
            $table->boolean('gallery_home_noindex')->default(true);
        });

        DB::statement("alter table studio_settings add constraint studio_settings_gallery_home_mode_check check (gallery_home_mode in ('none', 'redirect', 'overview'))");
    }

    public function down(): void
    {
        DB::statement('alter table studio_settings drop constraint studio_settings_gallery_home_mode_check');

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['gallery_home_mode', 'gallery_home_redirect_url', 'gallery_home_noindex']);
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'listed_on_home', 'home_pinned']);
            $table->dropConstrainedForeignId('project_type_id');
            $table->dropColumn(['listed_on_home', 'home_pinned']);
        });
    }
};
