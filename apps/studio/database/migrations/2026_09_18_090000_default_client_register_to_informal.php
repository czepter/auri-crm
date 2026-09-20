<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
     * Ein neues Studio startet beim Du. Bestehende Zeilen tragen ihr 'formal'
     * bereits als Wert — die vorige Migration hat es beim Anlegen der Spalte
     * eingeschrieben —, also ändert der neue Vorgabewert nur, womit das
     * nächste Studio anfängt, und kein einziges laufendes Postfach.
     */
    public function up(): void
    {
        DB::statement("alter table studio_settings alter column client_register set default 'informal'");
    }

    public function down(): void
    {
        DB::statement("alter table studio_settings alter column client_register set default 'formal'");
    }
};
