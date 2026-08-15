<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-06: §14 UStG wants the recipient's full name *and* address on an invoice.
 * C-01 shipped contacts with name, email and phone, which is everything the
 * CRM needed right up until the moment it had to print a legal document about
 * one of them.
 *
 * Nullable, and no backfill: every contact captured before an invoice existed
 * has no address, and inventing one would be worse than an empty line the
 * studio fills in when it bills them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('street')->nullable();
            $table->string('postcode', 32)->nullable();
            $table->string('city')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['street', 'postcode', 'city']);
        });
    }
};
