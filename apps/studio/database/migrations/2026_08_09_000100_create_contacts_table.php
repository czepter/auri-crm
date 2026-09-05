<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestampsTz();
        });

        // A partial unique index, so raw DDL — Laravel's unique() takes no
        // where clause. Vision §3.1: a couple sharing an inbox is one contact
        // holding that address, and a contact with no address simply has no
        // portal access. If the address could repeat, a P2 magic link would
        // resolve to more than one person and "who signed this contract" would
        // have no answer.
        DB::statement(
            'create unique index contacts_tenant_email_unique
             on contacts (tenant_id, email) where email is not null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
