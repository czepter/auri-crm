<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q-09: the third kind of line. unit_price null already separates a
     * priced position from an included one; selectable separates the line
     * the client switches from the two that are simply set when the quote
     * goes out.
     *
     * selectable false is today's state, so no existing line changes.
     *
     * outcome sits beside description rather than replacing it: description
     * is free text under the title and prints in the PDF, outcome is the one
     * line a tile has room for. Two lengths, two fields — a tile renders no
     * paragraphs.
     */
    public function up(): void
    {
        foreach (['quote_items', 'package_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('selectable')->default(false);
                $blueprint->boolean('selected')->default(false);
                $blueprint->string('outcome')->nullable();
                $blueprint->string('image_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quote_items', 'package_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['selectable', 'selected', 'outcome', 'image_path']);
            });
        }
    }
};
