<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-12: the one secret this feature has, and it is ours rather than the
 * studio's.
 *
 * weddingsuite.io's unified inbox connects the studio's own mailbox, which
 * means holding IMAP credentials or an OAuth mailbox grant — full-mailbox
 * blast radius bought for a thread view. This column is the alternative:
 * every inbound address is minted from it, so rotating it invalidates every
 * address ever handed out in a single write, and no mailbox password exists
 * anywhere to leak.
 *
 * Nullable: a deployment with no MX records has no inbound domain, and
 * minting addresses it cannot receive on would be worse than having none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('inbound_secret')->nullable();
            $table->timestampTz('inbound_secret_rotated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['inbound_secret', 'inbound_secret_rotated_at']);
        });
    }
};
