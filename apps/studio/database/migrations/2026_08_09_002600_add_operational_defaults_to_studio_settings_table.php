<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-13: the last two settings vision §8 wants off the environment
     * (timezone, locale — config('app.timezone') / config('app.locale')
     * stay as the FALLBACK, read only when the column here is null; see
     * StudioSettingsResolver::timezone()/locale()), plus working days,
     * which TaskTemplate::dueFor() needs to roll a due date off a day
     * nobody works.
     *
     * Presentation and due-date arithmetic only — every timestamp already
     * in this database stays exactly the UTC value it was stored as
     * (vision §8, "Dates and times"). Nothing here touches a `timestamptz`
     * column or rewrites a stored value.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            // Nullable, unset by default: StudioSettingsResolver falls
            // back to config('app.timezone') until a studio picks one.
            // Free-form IANA identifier — StudioSettingsController's
            // 'timezone' validation rule is what actually constrains it
            // (against PHP's own DateTimeZone list), not a check
            // constraint here.
            $table->string('timezone')->nullable();

            // Nullable, unset by default: falls back to config('app.locale')
            // ('de', not env-driven — see config/app.php's own comment on
            // why a fifth locale value is never a good idea here). This is
            // NOT that knob: it is Carbon's formatting locale, offering 'de'
            // itself (the same generic value the fallback resolves to, so
            // it is always a selectable, savable option) plus the three
            // DACH regional variants T-02's own country select already
            // offers (Deutschland/Österreich/Schweiz) — never the UI's
            // translation locale, so nothing here can produce an
            // untranslated screen.
            $table->string('locale')->nullable();

            // Every day, until a studio narrows it — never Monday-Friday by
            // default. A photography studio's working week routinely
            // includes Saturday weddings; defaulting away from that would
            // silently roll otherwise-correct due dates from an unmodified
            // install. ISO weekday integers, 1 (Monday) through 7 (Sunday).
            $table->jsonb('working_days')->default('[1,2,3,4,5,6,7]');
        });

        DB::statement("alter table studio_settings add constraint studio_settings_locale_check check (locale is null or locale in ('de', 'de_DE', 'de_AT', 'de_CH'))");
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'locale', 'working_days']);
        });
    }
};
