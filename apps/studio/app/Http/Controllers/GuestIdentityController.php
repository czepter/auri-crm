<?php

namespace App\Http\Controllers;

use App\Crm\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-01's proof of the api surface's guest-token guard: the first route
 * behind it, and the only one — everything the api surface actually does for
 * a caller (the gallery read API, the integration API, the MCP server) is a
 * later story against this same guard, not this route growing a body.
 */
class GuestIdentityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Contact $contact */
        $contact = $request->user('api');

        return response()->json([
            'contact' => [
                'public_id' => $contact->public_id,
                'name' => $contact->name,
            ],
        ]);
    }
}
