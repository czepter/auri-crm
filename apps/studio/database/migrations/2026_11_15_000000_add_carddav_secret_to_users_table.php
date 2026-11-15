<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted rather than hashed: the profile shows it again. Text
            // because the payload is longer than the secret; no index, since
            // lookup goes by e-mail.
            $table->text('carddav_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('carddav_secret');
        });
    }
};
