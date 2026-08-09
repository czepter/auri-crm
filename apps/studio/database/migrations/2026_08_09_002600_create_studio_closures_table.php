<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_closures', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // T-09's second availability source: a date range the studio is
            // closed, typed once by hand. No derived public-holiday calendar
            // — a wedding photographer works Feiertage, and a derived list
            // would warn on exactly the days that are booked.
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on');

            $table->timestampsTz();

            // The one query CheckDateAvailability::forDates() runs against
            // this table scans by tenant and range — see that class.
            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });

        DB::statement('alter table studio_closures add constraint studio_closures_range_check check (ends_on >= starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_closures');
    }
};
