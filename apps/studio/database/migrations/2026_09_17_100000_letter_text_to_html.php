<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #480. Einleitung und Schluss von Angebot und Rechnung sind Editor.js-HTML.
 * Was als Klartext gespeichert wurde, bekommt dieselbe Absatzform, die der
 * Renderer bisher daraus gebaut hat — das Dokument liest sich danach gleich.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'invoices'] as $table) {
            DB::table($table)
                ->where(fn ($query) => $query->whereNotNull('intro')->orWhereNotNull('outro'))
                ->orderBy('id')
                ->each(function (object $row) use ($table) {
                    DB::table($table)->where('id', $row->id)->update([
                        'intro' => $this->html($row->intro),
                        'outro' => $this->html($row->outro),
                    ]);
                });
        }
    }

    public function down(): void {}

    private function html(?string $text): ?string
    {
        if ($text === null || trim($text) === '' || str_starts_with(ltrim($text), '<')) {
            return $text;
        }

        return collect(preg_split('/\n{2,}/', trim($text)))
            ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }
};
