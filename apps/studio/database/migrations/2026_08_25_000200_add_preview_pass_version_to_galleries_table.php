<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R31: the fourth counter beside view_password_version, download_pin_
     * version and email_verification_version — same rule, a different
     * claim. A studio-issued draft-preview pass (App\Gallery\Support\
     * GalleryPreviewPass) is checked against this, not against
     * view_password_version, because solving the password gate and being
     * handed a look at unpublished pictures are different permissions.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->unsignedInteger('preview_pass_version')->default(1)->after('email_verification_version');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('preview_pass_version');
        });
    }
};
