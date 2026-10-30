<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** #562: automation_rules.event_class stores the event's class name. */
return new class extends Migration
{
    private const FROM = 'App\Crm\Events\ProjectBooked';

    private const TO = 'App\Crm\Events\CaseBooked';

    public function up(): void
    {
        DB::table('automation_rules')->where('event_class', self::FROM)->update(['event_class' => self::TO]);
    }

    public function down(): void
    {
        DB::table('automation_rules')->where('event_class', self::TO)->update(['event_class' => self::FROM]);
    }
};
