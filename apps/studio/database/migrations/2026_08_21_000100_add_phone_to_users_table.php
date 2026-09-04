<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The number a client rings. On the person rather than only on
     * studio_settings.portal_contact_phone: the studio-wide field is what
     * the portal says, this is who says it — and an owner who has never
     * opened /einstellungen/portal still has a number worth showing.
     */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('phone', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
