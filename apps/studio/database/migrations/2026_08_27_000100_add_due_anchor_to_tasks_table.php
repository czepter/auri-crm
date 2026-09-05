<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Which occasion the due date hangs off: a shoot's date, a stage
            // the lead entered, a status a shoot entered. Null for a task
            // typed against a fixed calendar day.
            //
            // due_on stays the one column every list reads. It is written
            // from the anchor rather than instead of it, so a moved shoot or
            // a late contract carries its tasks along without every reader
            // learning what an anchor is.
            $table->string('due_anchor')->nullable()->after('due_on');
            $table->smallInteger('due_offset_days')->nullable()->after('due_anchor');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['due_anchor', 'due_offset_days']);
        });
    }
};
