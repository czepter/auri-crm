<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wer eine nachbearbeitete Fassung nachliefert, will die Einordnung der
     * alten behalten — Fach und Titelbild-Rolle. Kein Ueberschreiben des
     * Keys: derselbe Key mit anderen Bytes bricht den Cache-Vertrag aus
     * GalleryImageController.
     */
    public function up(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->foreignId('replaces_asset_id')->nullable()->after('gallery_set_id')
                ->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_asset_id');
        });
    }
};
