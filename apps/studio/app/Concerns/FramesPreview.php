<?php

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait FramesPreview
{
    /**
     * A preview opened as a page of its own would be a bare sheet; the pane
     * it belongs to carries the same sheet next to its controls. Inside an
     * iframe the browser says "iframe" and gets the sheet.
     */
    private function framed(Request $request, string $back): ?RedirectResponse
    {
        if ($request->header('Sec-Fetch-Dest') !== 'document') {
            return null;
        }

        return redirect($back);
    }
}
