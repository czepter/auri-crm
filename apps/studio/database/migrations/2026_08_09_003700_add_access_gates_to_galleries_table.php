<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            // G-06's three gates. A null hash means the gate is off — no
            // separate "required" flag to drift out of sync with it. Every
            // gate carries its own version, bumped on every rotate() call and
            // nothing else; that counter, not the hash itself, is what a
            // granted claim is checked against (App\Gallery\Support\
            // GalleryAccessSession), so rotating one gate invalidates only
            // the claims minted before it — the other two gates' claims carry
            // their own version and are untouched.
            $table->string('view_password_hash')->nullable();
            $table->unsignedInteger('view_password_version')->default(1);

            $table->string('download_pin_hash')->nullable();
            $table->unsignedInteger('download_pin_version')->default(1);

            // The email gate has no secret to hash — it establishes identity,
            // not possession — so it keeps the boolean the other two don't
            // need. The version still exists: rotating it forces every
            // already-verified guest to reverify, the same invalidation rule
            // the other two gates follow.
            $table->boolean('requires_verified_email')->default(false);
            $table->unsignedInteger('email_verification_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn([
                'view_password_hash', 'view_password_version',
                'download_pin_hash', 'download_pin_version',
                'requires_verified_email', 'email_verification_version',
            ]);
        });
    }
};
