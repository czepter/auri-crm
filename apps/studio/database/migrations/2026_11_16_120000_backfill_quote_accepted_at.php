<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotes')->where('status', 'accepted')->whereNull('accepted_at')->update(['accepted_at' => DB::raw('updated_at')]);
    }

    public function down(): void {}
};
