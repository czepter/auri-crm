<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wer auf ein Archiv wartet.
     *
     * Ein ZipBuild gehört niemandem: die Zeile ist über
     * (gallery_version_id, scope, size) eindeutig, damit zwei Gäste, die
     * dasselbe herunterladen wollen, nicht zweimal 14 GB packen lassen.
     * Genau deshalb kann die Zustelladresse nicht an ihm hängen — es können
     * mehrere sein.
     *
     * notified_at ist der Riegel gegen die zweite Mail: ein Bau, der nach
     * einem Fehlschlag erneut läuft, erreicht Ready ein zweites Mal.
     */
    public function up(): void
    {
        Schema::create('zip_build_waiters', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('zip_build_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_guest_id')->constrained()->cascadeOnDelete();

            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'zip_build_id', 'gallery_guest_id'], 'zip_build_waiters_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_build_waiters');
    }
};
