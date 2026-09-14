<?php

namespace App\Integration\Http\Controllers;

use App\Catalog\Data\ServiceSuggestionData;
use App\Catalog\Models\Package;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * #403: the personal recommendations the admin's ServiceSuggestionController
 * writes and the contact page reads, opened to the api surface — same
 * updateOrCreate-per-package rule, same portal_offered-only source.
 */
class ServiceSuggestionController extends Controller
{
    public function index(Contact $contact): JsonResponse
    {
        $suggestions = ServiceSuggestion::query()
            ->where('contact_id', $contact->id)
            ->with(['package', 'suggestedBy'])
            ->get();

        return response()->json(['data' => $suggestions->map(ServiceSuggestionData::fromModel(...))->all()]);
    }

    public function store(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'package' => ['required', 'string', Rule::exists('packages', 'public_id')->where('portal_offered', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $package = Package::query()->where('public_id', $validated['package'])->sole();

        $suggestion = ServiceSuggestion::updateOrCreate(
            ['contact_id' => $contact->id, 'package_id' => $package->id],
            ['note' => $validated['note'] ?? null, 'suggested_by_id' => $this->actor($request)?->id],
        );

        AuditLogger::record(
            event: 'service_suggestion.saved',
            actor: $this->actor($request),
            context: ['package' => $package->public_id],
            subject: $suggestion->public_id,
        );

        return response()->json(['data' => ServiceSuggestionData::fromModel($suggestion->fresh(['package', 'suggestedBy']))], 201);
    }

    public function destroy(Request $request, ServiceSuggestion $serviceSuggestion): Response
    {
        AuditLogger::record(
            event: 'service_suggestion.removed',
            actor: $this->actor($request),
            subject: $serviceSuggestion->public_id,
        );

        $serviceSuggestion->delete();

        return response()->noContent();
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
