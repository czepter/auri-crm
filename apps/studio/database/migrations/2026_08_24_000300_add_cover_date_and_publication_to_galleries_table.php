<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cover_asset_id ersetzt das feste erste Bild, das Vorschau und Portal
     * bisher nahmen. event_date trägt das Datum einer Galerie, an der kein
     * Shooting hängt; die Constraint verhindert die zweite Datumswahrheit,
     * ohne dass eine App-Regel sie tragen muss. unpublished_at schließt das
     * Gate, ohne eine Version anzutasten.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('cover_asset_id')->nullable()->after('assets')
                ->constrained('assets')->nullOnDelete();
            $table->unsignedSmallInteger('cover_focal_x')->nullable()->after('cover_asset_id');
            $table->unsignedSmallInteger('cover_focal_y')->nullable()->after('cover_focal_x');

            $table->date('event_date')->nullable()->after('shoot_id');
            $table->timestampTz('unpublished_at')->nullable()->after('expires_at');
        });

        DB::statement(<<<'SQL'
            alter table galleries add constraint galleries_one_date
                check (shoot_id is null or event_date is null)
        SQL);
    }

    public function down(): void
    {
        DB::statement('alter table galleries drop constraint galleries_one_date');

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_asset_id');
            $table->dropColumn(['cover_focal_x', 'cover_focal_y', 'event_date', 'unpublished_at']);
        });
    }
};
