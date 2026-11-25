<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Data\ContractRowData;
use App\Contracts\Models\Contract;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Support\LiveUpdate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Verträge des ganzen Buchs, neben Angeboten, Rechnungen und
 * Gutschriften.
 */
class ContractIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $viewer = $request->user('staff');

        abort_unless($viewer?->can(Capability::ManageContracts->value), 403);

        $contracts = Contract::query()
            ->whereHas('case', fn ($project) => $project->visibleTo($viewer))
            ->with(['case.contacts', 'signature'])
            ->get();

        return Inertia::render('Contracts/Index', [
            'contracts' => LiveUpdate::live(ContractRowData::collect($contracts->map(ContractRowData::fromModel(...)))),
        ]);
    }
}
