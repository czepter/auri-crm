<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->string('action')->default('notice');
        });

        // #438: nothing automated reaches a client unapproved any more.
        DB::table('automation_rules')->where('recipient_type', 'client')->update(['action' => 'mail_draft']);

        // A draft is a run awaiting approval; what it would send is frozen on it.
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('contact_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn('action');
        });

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropColumn(['subject', 'body', 'contact_ids']);
        });
    }
};
