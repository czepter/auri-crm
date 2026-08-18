<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * #234: the typography a studio's portal and gallery are written in.
     *
     * `accent_color` and `logo_path` were already settable and the typeface
     * was not, so every portal read the same however different the studios
     * were. An enum column, never free text: the four cases are the four
     * pairs the product has licensed and self-hosts, and a fifth name here
     * would be a font nobody may serve — see App\Settings\Enums\FontPair.
     *
     * Nullable, and null means Instrument: a studio that never opens the
     * setting keeps exactly what it has today.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->string('font_pair')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('font_pair');
        });
    }
};
