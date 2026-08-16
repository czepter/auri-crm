<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-13: write it now, send it Monday.
 *
 * The row exists from the moment it is written rather than being held in a
 * queue payload until it fires — a scheduled mail is visible work sitting in
 * the thread, not a hidden timer nobody can see or cancel.
 *
 * Null for everything already sent, and for every message a client wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestampTz('scheduled_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('scheduled_for');
        });
    }
};
