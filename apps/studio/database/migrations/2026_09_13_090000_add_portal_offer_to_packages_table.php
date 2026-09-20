<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Was ein Paket im Portal ist. Ein Paket ist eine Vorlage für
     * Angebotspositionen; erst diese Felder machen daraus eine Leistung, die
     * ein Bestandskunde ansehen und anfragen kann. Sie stehen am Paket und
     * nicht in den Positionen, weil sie nicht in das Angebot gehören, das
     * daraus wird — Package::copyItems() kopiert sie deshalb nicht mit.
     *
     * portal_offered ist bewusst nicht is_active: stillgelegt heißt „nicht
     * mehr als Angebotsvorlage", und das soll einem Kunden nicht das Portal
     * umbauen. Die scopeActive()-Zusage, dass eine Stilllegung nichts
     * Bestehendes bewegt, bleibt damit stehen.
     *
     * project_type_key, weil eine Anfrage ohne Projekttyp keinen Shoot-Kind
     * und keine Projektfelder hat. Nullable: nur Pakete im Portal brauchen
     * ihn, und geprüft wird das dort, wo portal_offered gesetzt wird.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('portal_offered')->default(false);
            $table->string('portal_price_mode', 16)->default('exact');
            $table->boolean('portal_requires_date')->default(true);
            $table->string('portal_image_path')->nullable();
            $table->string('project_type_key')->nullable();

            $table->index(['tenant_id', 'portal_offered']);
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'portal_offered']);
            $table->dropColumn([
                'portal_offered', 'portal_price_mode',
                'portal_requires_date', 'portal_image_path', 'project_type_key',
            ]);
        });
    }
};
