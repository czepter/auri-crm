<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Sonstige" on its own is not a fact. Typ, Quelle and Eingangskanal each
 * keep a detail beside the enum case, so a lost enquiry can still be told
 * apart from a Tierfotografie one — the funnel splits on it.
 *
 * The presets are the details this studio keeps typing: written once in the
 * settings, offered as a datalist at capture. They are suggestions, never a
 * closed list, which is why they live as JSON on the settings row rather
 * than as a table of their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type_other')->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_other')->nullable();
            $table->string('captured_via_other')->nullable();
        });

        Schema::table('studio_settings', function (Blueprint $table) {
            $table->jsonb('lead_type_presets')->default('[]');
            $table->jsonb('lead_source_presets')->default('[]');
            $table->jsonb('lead_channel_presets')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('type_other'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['source_other', 'captured_via_other']));
        Schema::table('studio_settings', fn (Blueprint $table) => $table->dropColumn([
            'lead_type_presets', 'lead_source_presets', 'lead_channel_presets',
        ]));
    }
};
