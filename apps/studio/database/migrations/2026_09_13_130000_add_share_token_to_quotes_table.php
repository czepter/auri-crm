<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('share_token', 40)->nullable()->unique()->after('public_id');
        });

        // Quote::booted() only stamps a token on create — every row already
        // on file needs one written here, once, or its own share link reads
        // literally as "/angebot/null/teilen".
        DB::table('quotes')->whereNull('share_token')->orderBy('id')->each(function ($quote) {
            DB::table('quotes')->where('id', $quote->id)->update(['share_token' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
