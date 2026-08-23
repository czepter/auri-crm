<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wohin die Bytes gehören, steht beim Start der Sitzung fest und nicht
     * erst beim Ingest — der Worker weiß sonst nicht, welches Fach der
     * Mensch offen hatte, und die Wiederaufnahme nach einem Reload träfe ein
     * anderes.
     */
    public function up(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable()->after('gallery_id')
                ->constrained('gallery_sets')->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            update multipart_uploads u
               set gallery_set_id = s.id
              from gallery_sets s
             where s.gallery_id = u.gallery_id
               and s.position = 0
        SQL);
    }

    public function down(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_set_id');
        });
    }
};
