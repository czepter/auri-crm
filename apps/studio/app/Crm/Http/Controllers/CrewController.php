<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrewController extends Controller
{
    public function store(Request $request, Shoot $shoot): RedirectResponse
    {
        $this->authorize('assignCrew', $shoot);

        $validated = $request->validate([
            // The public id, never the key — the form only ever held CUID2s.
            'user' => ['required', 'string', Rule::exists('users', 'public_id')],
        ]);

        $user = User::findByPublicId($validated['user']);

        // Mirrors the unique index on (shoot_id, user_id). Without this the
        // database throws where the form should show a field error.
        $shoot->crew()->firstOrCreate(['user_id' => $user->id]);

        LiveUpdate::publish();

        return back();
    }

    public function destroy(CrewAssignment $assignment): RedirectResponse
    {
        $this->authorize('assignCrew', $assignment->shoot);

        $assignment->delete();

        LiveUpdate::publish();

        return back();
    }
}
