<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #521: closing ends the whole case, booked or not, so it is stored there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->timestampTz('closed_at')->nullable();
            $table->string('close_outcome')->nullable();
            $table->string('close_reason')->nullable();
            $table->index(['tenant_id', 'closed_at']);
        });

        DB::statement('alter table cases add constraint cases_close_check check ((closed_at is null) = (close_outcome is null) and (close_reason is null or closed_at is not null))');

        // outcome is the close marker since T-20 backfilled it; coalesce guards a row whose lost_at went missing.
        DB::statement(<<<'SQL'
            update cases c
               set closed_at = coalesce(l.lost_at, l.updated_at),
                   close_outcome = l.outcome,
                   close_reason = l.lost_reason
              from leads l
             where l.case_id = c.id
               and l.outcome is not null
        SQL);

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'lost_at', 'lost_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestampTz('lost_at')->nullable()->index();
            $table->string('lost_reason')->nullable();
            $table->string('outcome')->nullable();
            $table->index(['tenant_id', 'outcome']);
        });

        DB::statement(<<<'SQL'
            update leads l
               set lost_at = c.closed_at,
                   outcome = c.close_outcome,
                   lost_reason = c.close_reason
              from cases c
             where l.case_id = c.id
               and c.closed_at is not null
        SQL);

        DB::statement('alter table cases drop constraint cases_close_check');

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'closed_at']);
            $table->dropColumn(['closed_at', 'close_outcome', 'close_reason']);
        });
    }
};
