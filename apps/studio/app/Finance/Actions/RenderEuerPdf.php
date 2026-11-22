<?php

namespace App\Finance\Actions;

use App\Finance\Models\FiscalYear;
use App\Finance\Support\EuerReport;
use App\Models\StudioSettings;
use App\Support\GotenbergClient;
use Illuminate\Support\Facades\Storage;

class RenderEuerPdf
{
    public function __construct(private readonly EuerReport $report, private readonly GotenbergClient $gotenberg) {}

    /** The PDF of a year as it reads now, or as its snapshot reads for a locked one. */
    public function __invoke(int $year): string
    {
        $fiscal = FiscalYear::query()->where('year', $year)->first();
        $snapshot = $fiscal?->isLocked() ? $fiscal->snapshot : null;

        return $this->gotenberg->htmlToPdf(view('pdf.euer', [
            'studio' => StudioSettings::current(),
            'report' => $this->report->forYear($year),
            'appendix' => $snapshot !== null ? $snapshot : $this->report->appendix($year),
            'createdOn' => ($fiscal?->locked_at ?? now())->format('d.m.Y'),
        ])->render());
    }

    /** The stored PDF of a locked year, made from its snapshot and kept when it was missing. */
    public function stored(FiscalYear $fiscal): string
    {
        $disk = Storage::disk('s3');

        if ($fiscal->pdf_path !== null && $disk->exists($fiscal->pdf_path)) {
            return $disk->get($fiscal->pdf_path);
        }

        $pdf = ($this)($fiscal->year);
        $path = "finance/{$fiscal->tenant_id}/euer-{$fiscal->year}.pdf";

        $disk->put($path, $pdf);
        $fiscal->update(['pdf_path' => $path]);

        return $pdf;
    }
}
