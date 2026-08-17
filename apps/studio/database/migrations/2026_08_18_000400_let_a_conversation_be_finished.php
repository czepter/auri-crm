<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * C-14 (#250): the inbox groups conversations by working state —
     * unanswered, waiting on the client, finished.
     *
     * The first two are derived and cannot go stale: who spoke last is the
     * newest message's own author. The third is not derivable from anything —
     * a conversation that needs no further reply looks exactly like one
     * waiting for one — so it is a decision somebody takes, and this column
     * records it.
     *
     * The message it was closed on, not the moment: a reply and a click on
     * "Erledigt" inside the same second are common, and `timestamps()` stores
     * whole seconds, so a timestamp comparison decides that case by luck. The
     * newest message either is the one it was filed on or it is not.
     */
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->foreignId('closed_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_message_id');
        });
    }
};
