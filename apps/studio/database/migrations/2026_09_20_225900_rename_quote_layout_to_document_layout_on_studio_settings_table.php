<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->renameColumn('quote_layout', 'document_layout');
        });

        DB::statement('alter table studio_settings rename constraint studio_settings_quote_layout_check to studio_settings_document_layout_check');
    }

    public function down(): void
    {
        DB::statement('alter table studio_settings rename constraint studio_settings_document_layout_check to studio_settings_quote_layout_check');

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->renameColumn('document_layout', 'quote_layout');
        });
    }
};
