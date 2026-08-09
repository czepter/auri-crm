<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\Shoot;
use App\Crm\Models\ShotListItem;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShotListItemController extends Controller
{
    public function store(Request $request, Shoot $shoot): RedirectResponse
    {
        $this->authorize('manageShotList', $shoot);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $shoot->shotListItems()->create($validated);

        return back();
    }

    /**
     * Checking off is a toggle rather than a delete, the same reasoning as
     * Task::complete(): the shot survives being un-checked by mistake.
     */
    public function toggle(ShotListItem $item): RedirectResponse
    {
        $this->authorize('manageShotList', $item->shoot);

        $done = ! $item->isCheckedOff();

        $item->forceFill(['checked_off_at' => $done ? now() : null])->save();

        AuditLogger::record(
            event: $done ? 'shot_list_item.checked' : 'shot_list_item.unchecked',
            actor: auth('staff')->user(),
            context: ['label' => $item->label],
            subject: $item->public_id,
        );

        return back();
    }

    public function destroy(ShotListItem $item): RedirectResponse
    {
        $this->authorize('manageShotList', $item->shoot);

        $item->delete();

        return back();
    }
}
