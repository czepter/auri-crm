<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-04 (#223): a client writes to the studio, not to an order. Whether a
     * conversation belongs to a booking is a property of the thread, never a
     * precondition for starting one — otherwise nobody can ask "do you still
     * have anything free in autumn?" before the booking they are asking
     * about exists.
     *
     * The foreign key stays: a thread that names a project must name one that
     * is really there. Only the requirement to name one at all goes.
     */
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
