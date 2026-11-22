<?php

namespace App\Finance\Jobs;

use App\Finance\Actions\RenderEuerPdf;
use App\Finance\Models\FiscalYear;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Renders and stores a locked year's PDF; guarded on `pdf_path`, like the invoice jobs. */
class RenderEuerPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly FiscalYear $fiscalYear) {}

    public function handle(RenderEuerPdf $render): void
    {
        $fiscal = $this->fiscalYear->fresh();

        if ($fiscal === null || $fiscal->pdf_path !== null) {
            return;
        }

        $render->stored($fiscal);
    }
}
