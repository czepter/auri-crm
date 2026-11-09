<?php

namespace App\Gallery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Löscht alles unter dem Präfix der Galerie (Originale, Derivate, ZIPs) und
 * die Schlüssel außerhalb davon. Trägt nur Zeichenketten, keine Modelle: die
 * Zeilen sind beim Lauf schon weg. Ein fehlendes Objekt ist kein Fehler, und
 * ein zweiter Lauf findet nichts mehr.
 */
class PurgeGalleryObjects implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param  list<string>  $keys */
    public function __construct(public readonly string $prefix, public readonly array $keys = [])
    {
        $this->onQueue('media.ingest');
    }

    public function handle(): void
    {
        $disk = Storage::disk('s3');

        $disk->deleteDirectory(rtrim($this->prefix, '/'));

        foreach (array_chunk($this->keys, 500) as $chunk) {
            $disk->delete($chunk);
        }
    }
}
