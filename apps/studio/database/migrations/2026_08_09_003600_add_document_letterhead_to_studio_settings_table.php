<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // T-12: the PDF shell a Contract or Quote's resolved body is wrapped
        // in — App\Documents\Support\DocumentRenderer — shared across every
        // template rather than pasted into each one. Null renders as no
        // letterhead/footer at all, same "unset is a valid, working state"
        // convention as widerruf_text.
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->text('document_letterhead_html')->nullable();
            $table->text('document_footer_html')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['document_letterhead_html', 'document_footer_html']);
        });
    }
};
