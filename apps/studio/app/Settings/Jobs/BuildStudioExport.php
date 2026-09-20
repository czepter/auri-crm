<?php

namespace App\Settings\Jobs;

use App\Settings\Actions\PackStudioExport;
use App\Settings\Models\StudioExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Auf media.zip wie BuildZip: ein Archiv mit Bildern braucht dieselbe Zeit. */
class BuildStudioExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly StudioExport $export) {}

    public function handle(PackStudioExport $pack): void
    {
        $pack($this->export);
    }
}
