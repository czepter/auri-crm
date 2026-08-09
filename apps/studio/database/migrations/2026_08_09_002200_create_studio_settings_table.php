<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_settings', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // The only column T-01 adds. Vision §12's config service needs
            // typed columns rather than a key-value bag to prove out, and this
            // is the smallest field that is genuinely one — the studio's
            // display name, T-02's own "Anzeigename"
            // (mockups/admin/14-einstellungen.html). T-02 and T-03 extend this
            // table with the rest of the Studio-Profil and Zahlungsdaten & USt
            // panes; nothing here is a permanent shape.
            $table->string('display_name')->nullable();

            $table->timestampsTz();

            // One row per tenant — never a history, never a second row. Q-04's
            // and M-02's snapshot obligations already answer "what did this
            // say when it mattered"; versioning this table too would be a
            // second answer to a question the documents already answer.
            $table->unique('tenant_id');
        });

        // Seeded here, not in a seeder some environments forget to run: a
        // fresh install (and every test, via RefreshDatabase) gets its one
        // settings row the moment this table exists, so the resolver never
        // has to reason about a tenant with none. One studio today
        // (config('tenancy.tenant_id')) — see BelongsToTenant — so one insert.
        DB::table('studio_settings')->insert([
            'tenant_id' => config('tenancy.tenant_id'),
            'public_id' => (string) new Cuid2,
            'display_name' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_settings');
    }
};
