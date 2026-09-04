<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    /**
     * Sets waren bis hier ein Schlüssel im Asset-Eintrag der json-Spalte,
     * den nichts schrieb. Als Zeile tragen sie das, was ein Fach braucht:
     * eine Reihenfolge in der Schiene und eine Sortierregel für ihren
     * Inhalt. Die Regel sitzt am Set, nicht an der Galerie — ein
     * Highlight-Set will die Aufnahmezeit, ein Fotobox-Set den Dateinamen
     * des Automaten.
     */
    public function up(): void
    {
        Schema::create('gallery_sets', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('position')->default(0);

            $table->string('sort_key')->default('taken_at');
            $table->string('sort_dir')->default('asc');

            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'name']);
            $table->index(['tenant_id', 'gallery_id', 'position']);
        });

        // Jede bestehende Galerie bekommt ihr Fach, bevor der Fremdschlüssel
        // an assets not null wird (Task 2). Zeilenweise und an GallerySet
        // vorbei, direkt auf die Tabelle: die public_id trägt denselben
        // Cuid2 wie jede andere, aber die Migration hängt nicht von der
        // heutigen Form des Models ab.
        $now = now();

        $galleries = DB::table('galleries')
            ->where('is_external', false)
            ->select(['id', 'tenant_id'])
            ->orderBy('id')
            ->get();

        foreach ($galleries as $gallery) {
            DB::table('gallery_sets')->insert([
                'tenant_id' => $gallery->tenant_id,
                'public_id' => (string) new Cuid2,
                'gallery_id' => $gallery->id,
                'name' => 'Highlights',
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_sets');
    }
};
