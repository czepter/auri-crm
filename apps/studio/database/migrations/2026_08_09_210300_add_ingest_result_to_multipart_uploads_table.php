<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            // G-03: set once App\Gallery\Actions\IngestCompletedUpload has run
            // to a conclusion (Ready or Failed asset) for this upload — never
            // reset. A row with completed_at set but processed_at still null
            // is exactly what a stuck/never-dispatched ingest job looks like.
            $table->timestampTz('processed_at')->nullable()->after('completed_at');

            // Set on every conclusion, including a duplicate (checksum
            // already known in this gallery) and a Failed one — see
            // IngestCompletedUpload for both.
            $table->foreignId('asset_id')->nullable()->after('processed_at')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
            $table->dropColumn('processed_at');
        });
    }
};
