<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('assistant');
            $table->text('mfa_secret')->nullable();
            $table->timestampTz('mfa_confirmed_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
