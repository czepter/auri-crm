<?php

namespace App\Payments\Support;

use Closure;
use Illuminate\Support\Facades\Storage;

/**
 * #416, GoBD Rz. 59 Beispiel 4 and Rz. 125: the PDF a client holds has to be
 * reproducible from the data of the day it was issued. Every figure and every
 * address on an invoice comes from one merge array
 * (App\Documents\Support\DocumentMergeDataBuilder), so freezing that array
 * beside the file freezes the document — a re-render after the PDF is gone
 * reads it back rather than asking today's settings, today's contact address
 * and today's quote what they say now.
 *
 * Written on the first render and never again, which is the same guard
 * RenderInvoiceJob puts on the PDF itself.
 */
final class MergeDataSnapshot
{
    public static function pathFor(string $documentPath): string
    {
        return $documentPath.'.json';
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function remember(string $documentPath, Closure $build): array
    {
        $disk = Storage::disk('local');
        $path = self::pathFor($documentPath);

        if ($disk->exists($path)) {
            $stored = json_decode((string) $disk->get($path), true);

            if (is_array($stored)) {
                return $stored;
            }
        }

        $data = $build();

        $disk->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $data;
    }
}
