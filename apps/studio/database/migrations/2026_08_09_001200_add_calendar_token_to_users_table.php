<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The only credential a calendar app can carry: a subscription URL
            // is fetched by software that cannot log in, so the secret lives
            // in the path.
            //
            // 64 hex characters from random_bytes, which is why it is not
            // hashed like a password would be: the server has to be able to
            // find the row from the URL. Its defence is length and the fact
            // that it opens exactly one read-only feed of one person's own
            // shoots, and nothing else.
            //
            // Null until someone asks for their feed, so an account that never
            // subscribes never carries a live credential.
            $table->char('calendar_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
