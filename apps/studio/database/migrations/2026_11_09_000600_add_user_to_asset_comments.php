<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_comments', function (Blueprint $table) {
            $table->string('guest_identifier')->nullable()->change();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_comments', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
