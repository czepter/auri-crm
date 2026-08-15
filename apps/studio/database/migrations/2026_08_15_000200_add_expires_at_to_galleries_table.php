<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a delivered gallery stops being available. Distinct from
 * gallery_email_verifications.expires_at, which times out one visitor's proof
 * of address and has nothing to do with how long the photographs stay up.
 *
 * Null means kept indefinitely, which is what every existing gallery is — so
 * this backfills nothing and changes no current behaviour. Only the Übersicht
 * reads it today: a gallery running out is something the studio may want to
 * act on before it does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->timestampTz('expires_at')->nullable();
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
