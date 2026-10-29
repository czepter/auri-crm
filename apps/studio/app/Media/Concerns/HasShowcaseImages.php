<?php

namespace App\Media\Concerns;

use App\Media\Support\ImageVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Q-10: die Bilder, aus denen das Reglerraster gekachelt wird — am Paket der
 * Startsatz, am Angebot das, was der Kunde sieht. Dieselbe Teilung wie
 * HasHourlyPricing daneben, die die Stundenwerte hält, auf die sich das
 * Raster bezieht.
 *
 * Beim Kopieren gehen die Bytes mit, nicht nur die Pfade: sonst nimmt das
 * Entfernen eines Bildes am Paket die Datei unter jedem längst versendeten
 * Angebot weg, das daraus entstanden ist.
 */
trait HasShowcaseImages
{
    /** @return list<string> */
    public function showcasePaths(): array
    {
        return array_values($this->showcase_paths ?? []);
    }

    /**
     * Abgelegt wird nicht, was ankommt: eine Kacheldatei aus der Kamera sind
     * acht Megapixel für ein paar hundert Pixel Raster. 800 breit als WebP,
     * über dieselbe GD-Strecke, die die Galerie für ihre Varianten fährt.
     */
    public function addShowcaseImage(UploadedFile $file): void
    {
        $path = $this->showcaseDirectory().'/'.Str::random(40).'.webp';

        Storage::disk('local')->put($path, ImageVariant::resizeAndEncode($file->get(), 800, 'webp'));

        $this->update(['showcase_paths' => [...$this->showcasePaths(), $path]]);
    }

    /** Ein Index, den niemand hält, ist ein No-op. */
    public function removeShowcaseImage(int $index): void
    {
        $paths = $this->showcasePaths();

        if (! array_key_exists($index, $paths)) {
            return;
        }

        Storage::disk('local')->delete($paths[$index]);
        unset($paths[$index]);

        $this->update(['showcase_paths' => array_values($paths)]);
    }

    /**
     * Kopiert die Dateien in den Ordner des Ziels und liefert die neuen
     * Pfade — das Ziel ist noch nicht gespeichert worden, wenn der Aufrufer
     * sie schreibt.
     *
     * @return list<string>
     */
    public function copyShowcasePaths(string $directory): array
    {
        return array_values(array_filter(array_map(function (string $path) use ($directory) {
            $copy = $directory.'/'.basename($path);

            return Storage::disk('local')->copy($path, $copy) ? $copy : null;
        }, $this->showcasePaths())));
    }

    public function showcaseDirectory(): string
    {
        return "showcase/{$this->tenant_id}/{$this->public_id}";
    }
}
