<?php

use App\Gallery\Support\GalleryPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->jsonb('permissions')->nullable();
            $table->boolean('intro_enabled')->default(false);
        });

        // Bestehende Galerien behalten, was ihre Gäste heute dürfen: die
        // alten Schalter gelten für beide Gruppen.
        DB::table('galleries')->orderBy('id')->each(function (object $row) {
            $permissions = GalleryPermissions::defaults();

            foreach (['client', 'guest'] as $audience) {
                $permissions[$audience]['favorites'] = (bool) $row->lists_enabled;
                $permissions[$audience]['comments'] = (bool) $row->comments_enabled;
            }

            DB::table('galleries')->where('id', $row->id)->update(['permissions' => json_encode($permissions)]);
        });

        // Bestehende Sets sind für Gäste sichtbar, neue sind es nicht.
        Schema::table('gallery_sets', function (Blueprint $table) {
            $table->string('access')->default('guests');
        });
        DB::statement("alter table gallery_sets alter column access set default 'internal'");
        DB::statement("alter table gallery_sets add constraint gallery_sets_access_check check (access in ('internal', 'guests'))");

        Schema::create('asset_hides', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key');
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'asset_key', 'contact_id'], 'asset_hides_unique_mark');
        });

        // Ein Archiv ohne interne Sets ist keines, das ein Gast bekommen darf.
        Schema::table('zip_builds', function (Blueprint $table) {
            $table->boolean('guests')->default(false);

            $table->dropUnique('zip_builds_unique_request');
            $table->unique(['tenant_id', 'gallery_version_id', 'scope', 'size', 'gallery_list_id', 'guests'], 'zip_builds_unique_request');
        });
    }

    public function down(): void
    {
        Schema::table('zip_builds', function (Blueprint $table) {
            $table->dropUnique('zip_builds_unique_request');
            $table->dropColumn('guests');
            $table->unique(['tenant_id', 'gallery_version_id', 'scope', 'size', 'gallery_list_id'], 'zip_builds_unique_request');
        });

        Schema::dropIfExists('asset_hides');
        Schema::table('gallery_sets', fn (Blueprint $table) => $table->dropColumn('access'));
        Schema::table('galleries', fn (Blueprint $table) => $table->dropColumn(['permissions', 'intro_enabled']));
    }
};
