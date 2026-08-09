<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Null until the studio cuts a contact off outright (A-04). Once
            // set, every unconsumed magic link and any session already open
            // elsewhere is denied on its very next request — see
            // EnsureAudience and ClientAccess::scopeLive. Nothing in this
            // story ever unsets it: re-granting access is not in scope.
            $table->timestampTz('portal_access_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('portal_access_revoked_at');
        });
    }
};
