<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            // Mirrors leads.stage_entered_at. P-04's board reads it for
            // staleness — "elf Tage in Auswahl" — which cannot be computed
            // without it, and which is far cheaper to add here than to
            // backfill once the column has history.
            //
            // Added nullable so the existing rows have somewhere to land; the
            // statements below fill them and close the column.
            $table->timestampTz('status_entered_at')->nullable();

            // Cancellation is not a state. A tenth enum case would erase which
            // pipeline position the shoot died in — the same reasoning that
            // keeps Lost out of LeadStage. See Lead::$lost_at.
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();

            // Every derived read — C-06's header, P-04's board — filters on
            // this, always within one tenant.
            $table->index(['tenant_id', 'cancelled_at']);
        });

        DB::statement('update shoots set status_entered_at = created_at where status_entered_at is null');

        // The default covers the writers that never go through
        // AdvanceShootStatus, CaptureInquiry above all: a shoot arrives at
        // requested the moment it is created, and that is a status entry.
        DB::statement('alter table shoots alter column status_entered_at set default now()');
        DB::statement('alter table shoots alter column status_entered_at set not null');
    }

    public function down(): void
    {
        Schema::table('shoots', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'cancelled_at']);
            $table->dropColumn(['status_entered_at', 'cancelled_at', 'cancelled_reason']);
        });
    }
};
