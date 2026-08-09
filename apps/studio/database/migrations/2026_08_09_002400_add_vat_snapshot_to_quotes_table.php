<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // All four stay null until Q-06 sends the quote and calls
            // Quote::snapshotVat() — a draft has decided nothing yet, so it
            // has nothing to freeze. Once set, a quote's own read path never
            // falls through to StudioSettingsResolver again: that is the
            // whole point of a snapshot (see Quote::snapshotVat()'s docblock).
            $table->string('vat_mode')->nullable()->after('net');
            $table->unsignedInteger('vat_rate')->nullable()->after('vat_mode');
            $table->text('vat_note')->nullable()->after('vat_rate');
            $table->unsignedInteger('vat_amount')->nullable()->after('vat_note');
        });

        // Same belt-and-suspenders as quotes_net_check and
        // studio_settings_vat_mode_check: nullable so a draft passes, but a
        // populated value is still confined to the two modes T-03 defined.
        DB::statement("alter table quotes add constraint quotes_vat_mode_check check (vat_mode is null or vat_mode in ('standard', 'small_business'))");
        DB::statement('alter table quotes add constraint quotes_vat_rate_check check (vat_rate is null or (vat_rate >= 0 and vat_rate <= 10000))');
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['vat_mode', 'vat_rate', 'vat_note', 'vat_amount']);
        });
    }
};
