<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Client-visible, full stop. No visibility flag lives here and never
        // will — C-07's InternalNote is its own table for exactly that reason
        // (see its migration). Every row in this table is something the
        // client on the thread may read.
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();

            // Exactly one of these is set — the message's one author, a
            // client or a staff member, never both and never neither. The
            // model's own creating() guard enforces it in PHP; the check
            // constraint below makes it true of the row even for a write
            // that bypasses the model.
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users');

            $table->text('body');
            $table->timestampsTz();

            $table->index(['tenant_id', 'thread_id']);
        });

        DB::statement(
            'alter table messages add constraint messages_one_author_check '.
            'check ((contact_id is not null) <> (author_id is not null))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
