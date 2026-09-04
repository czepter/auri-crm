<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reader the in_app transport never had. Every staff-facing notification
 * writes a row here whatever the mail preference says, so this list is the
 * complete record and the mail is the interruption.
 *
 * Ungated, like the profile: these are this person's own rows, and the
 * audience that produced them was already narrowed by StudioAudience.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => $request->user('staff')->inAppNotifications()
                ->limit(100)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->public_id,
                    'title' => $row->data['title'] ?? '',
                    'body' => $row->data['body'] ?? '',
                    'url' => $row->data['url'] ?? null,
                    'readAt' => $row->read_at?->toIso8601String(),
                    'createdAt' => $row->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /** All of them, not one: the list is read in one pass or not at all. */
    public function markRead(Request $request): RedirectResponse
    {
        $request->user('staff')->inAppNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
