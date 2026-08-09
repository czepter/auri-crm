<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            // X-02: a legacy gallery that isn't worth re-ingesting is linked
            // instead of backfilled — is_external marks the row as pointing
            // at assets hosted elsewhere rather than owning any of its own,
            // and external_url is where a guest is sent. A backfilled row
            // leaves both untouched: is_external stays false, external_url
            // stays null, and Gallery::publish() works exactly as G-05 left
            // it.
            $table->boolean('is_external')->default(false)->after('assets');
            $table->string('external_url')->nullable()->after('is_external');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['is_external', 'external_url']);
        });
    }
};
