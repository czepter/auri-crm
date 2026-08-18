<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * G-01 (#229): two roles, one screen. A guest writes as "Gast"; a
     * signed-in client writes under their own name.
     *
     * Both tables have only ever had guest_identifier — a string a browser
     * minted for itself — so a couple commenting on their own gallery
     * appeared as an anonymous stranger to the studio reading it.
     * guest_identifier stays and stays required: a client is also a visitor
     * with a session, and it is what keeps the (version, key, guest) unique
     * index doing its job.
     */
    public function up(): void
    {
        Schema::table('asset_comments', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('asset_favorites', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_favorites', fn (Blueprint $table) => $table->dropColumn('contact_id'));
        Schema::table('asset_comments', fn (Blueprint $table) => $table->dropColumn('contact_id'));
    }
};
