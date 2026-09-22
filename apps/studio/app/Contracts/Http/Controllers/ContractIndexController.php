<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Data\ContractRowData;
use App\Contracts\Models\Contract;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Verträge des ganzen Buchs, neben Angeboten, Rechnungen und
 * Gutschriften. Entwürfe bleiben draußen: was niemand bekommen hat, steht
 * am Projekt, nicht in den Unterlagen.
 */
class ContractIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $viewer = $request->user('staff');

        abort_unless($viewer?->can(Capability::ManageContracts->value), 403);

        $contracts = Contract::query()
            ->whereNotNull('sent_at')
            ->whereHas('project', fn ($project) => $project->visibleTo($viewer))
            ->with(['project.contacts', 'signature'])
            ->orderByDesc('sent_at')
            ->get();

        return Inertia::render('Contracts/Index', [
            'contracts' => ContractRowData::collect($contracts->map(ContractRowData::fromModel(...))),
        ]);
    }
}
