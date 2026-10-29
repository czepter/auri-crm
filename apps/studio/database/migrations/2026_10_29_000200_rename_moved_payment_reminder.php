<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** #560: PaymentReminder moved from Portal to Payments; see 2026_10_29_000100 for why the columns matter. */
return new class extends Migration
{
    private const FROM = 'App\Portal\Notifications\PaymentReminder';

    private const TO = 'App\Payments\Notifications\PaymentReminder';

    public function up(): void
    {
        $this->rename(self::FROM, self::TO);
    }

    public function down(): void
    {
        $this->rename(self::TO, self::FROM);
    }

    private function rename(string $from, string $to): void
    {
        DB::table('notification_preferences')->where('notification_class', $from)->update(['notification_class' => $to]);
        DB::table('in_app_notifications')->where('type', $from)->update(['type' => $to]);
    }
};
