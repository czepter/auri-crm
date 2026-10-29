<?php

namespace App\Documents\Http\Controllers;

use App\Documents\Models\DocumentView;
use App\Http\Controllers\Controller;
use App\Payments\Http\Controllers\InvoiceController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentViewController extends Controller
{
    public function update(Request $request, DocumentView $view): RedirectResponse
    {
        if ($view->quote !== null) {
            $this->authorize('manageQuotes', $view->quote->case);
        } else {
            InvoiceController::authorizeProject($view->invoice->case);
        }

        $view->update($request->validate(['counts' => ['required', 'boolean']]));

        return back();
    }
}
