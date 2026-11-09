<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMES = [
        'shoot-reminder-7-days-before' => ['Shooting-Erinnerung — 7 Tage vorher', 'Shooting-Erinnerung'],
        'quote-follow-up' => ['Angebot — Erinnerung vor Ablauf', 'Angebot läuft ab'],
        'quote-no-reply' => ['Angebot — ohne Antwort', 'Angebot ohne Antwort'],
        'questionnaire-before-wedding' => ['Ablauf-Fragebogen — vor der Hochzeit', 'Ablauf-Fragebogen'],
        'installment-due-soon' => ['Rate — vor Fälligkeit', 'Ratenerinnerung'],
        'gallery-expiry-soon' => ['Galerie — vor Ablauf', 'Galerie läuft ab'],
        'crew-briefing-day-before' => ['Crew — Tag vor dem Shooting', 'Crew-Briefing'],
        'packing-list' => ['Packliste', 'Ausrüstung packen'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $key => [$old, $new]) {
            DB::table('automation_rules')->where('key', $key)->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $key => [$old, $new]) {
            DB::table('automation_rules')->where('key', $key)->where('name', $new)->update(['name' => $old]);
        }
    }
};
