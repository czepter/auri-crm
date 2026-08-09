<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who is on a thread — written the moment they post to it (see
        // App\Engagement\Actions\PostMessage). Exactly one of contact_id /
        // user_id per row, the same shape messages.contact_id/author_id
        // takes and for the same reason.
        Schema::create('thread_participants', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->unique(['thread_id', 'contact_id']);
            $table->unique(['thread_id', 'user_id']);
        });

        DB::statement(
            'alter table thread_participants add constraint thread_participants_one_party_check '.
            'check ((contact_id is not null) <> (user_id is not null))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_participants');
    }
};
