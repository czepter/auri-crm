<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            // T-10's third availability source: a person's own recorded time
            // away — holiday, another job, parental leave. No reason column:
            // see the model's own doc comment for why category is a closed
            // enum rather than free text.
            $table->foreignId('user_id')->constrained();
            $table->string('category');
            $table->date('starts_on');
            $table->date('ends_on');

            $table->timestampsTz();

            // Same shape as studio_closures' own index — CheckDateAvailability
            // scans this table by tenant and range too, see that class.
            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });

        DB::statement('alter table absences add constraint absences_range_check check (ends_on >= starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
