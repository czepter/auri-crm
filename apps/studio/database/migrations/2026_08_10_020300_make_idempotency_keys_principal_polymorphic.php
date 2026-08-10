<?php

use App\Integration\Models\ApiToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * N-04: idempotency_keys' claim was keyed to api_token_id alone because
 * I-03's only api-surface caller was ever an ApiToken. N-04 gives the
 * surface a second kind of caller — a staff User authenticated by their own
 * OAuth token — so the claim now keys off whichever principal made the
 * call, the same morphTo shape App\Automation\Models\AutomationRun already
 * uses for its own polymorphic anchor. Every row this app has ever written
 * was an ApiToken claim, so the backfill is unconditional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->nullableMorphs('principal');
        });

        DB::table('idempotency_keys')->update([
            'principal_type' => ApiToken::class,
            'principal_id' => DB::raw('api_token_id'),
        ]);

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('idempotency_keys_unique');
            $table->dropConstrainedForeignId('api_token_id');
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->unique(['tenant_id', 'principal_type', 'principal_id', 'idempotency_key'], 'idempotency_keys_principal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('idempotency_keys_principal_unique');
            $table->foreignId('api_token_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('idempotency_keys')->where('principal_type', ApiToken::class)->update([
            'api_token_id' => DB::raw('principal_id'),
        ]);

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropMorphs('principal');
            $table->unique(['tenant_id', 'api_token_id', 'idempotency_key'], 'idempotency_keys_unique');
        });
    }
};
