<?php

namespace App\Concerns;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

trait FramesPreview
{
    /**
     * A preview opened as a page of its own would be a bare sheet without
     * navigation; inside an iframe the browser says "iframe" and gets the sheet.
     */
    private function framed(Request $request, string $label, string $back, bool $phone = false): ?Response
    {
        if ($request->header('Sec-Fetch-Dest') !== 'document') {
            return null;
        }

        return Inertia::render('Settings/PreviewPage', ['label' => $label, 'back' => $back, 'src' => $request->fullUrl(), 'phone' => $phone]);
    }
}
