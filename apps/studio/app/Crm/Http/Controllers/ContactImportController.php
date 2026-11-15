<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\ImportContacts;
use App\Crm\Enums\SocialPlatform;
use App\Crm\Models\Contact;
use App\Crm\Support\ContactCsv;
use App\Crm\Support\SocialProfiles;
use App\Http\Controllers\Controller;
use App\Settings\Rules\ValidFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The file is read from the request's temporary upload and never stored. */
class ContactImportController extends Controller
{
    public function create(): Response
    {
        $this->authorize('update', new Contact);

        return Inertia::render('Contacts/Import', [
            'socialPlatforms' => collect(SocialPlatform::cases())
                ->map(fn (SocialPlatform $platform) => ['value' => $platform->value, 'label' => $platform->label()])->all(),
        ]);
    }

    public function preview(Request $request, ImportContacts $import): JsonResponse
    {
        $this->authorize('update', new Contact);

        ['headers' => $headers, 'rows' => $rows] = $this->read($request);
        $mapping = $this->mapping($request, $headers);
        $plan = $import->plan($rows, $mapping, $this->rules());

        return response()->json([
            'headers' => $headers,
            'samples' => array_map(fn (int $column) => collect($rows)->pluck($column)->filter()->first(), array_keys($headers)),
            'mapping' => $mapping,
            'rows' => array_map(fn (array $row) => array_diff_key($row, ['attributes' => true]), $plan),
        ]);
    }

    public function store(Request $request, ImportContacts $import): RedirectResponse
    {
        $this->authorize('update', new Contact);

        ['headers' => $headers, 'rows' => $rows] = $this->read($request);

        $import($rows, $this->mapping($request, $headers), $this->rules(), auth('staff')->user());

        return redirect('/kontakte');
    }

    /**
     * ContactController::store's rules, less the uniqueness the import reports as a skip.
     *
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64', new ValidFormat('phone')],
            'street' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            ...SocialProfiles::rules(),
        ];
    }

    /** @return array{headers: list<string>, rows: array<int, list<string>>} */
    private function read(Request $request): array
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', Rule::in(ContactCsv::fields())],
        ]);

        $csv = ContactCsv::read($request->file('file')->getRealPath());

        if ($csv['rows'] === []) {
            throw ValidationException::withMessages(['file' => 'Die Datei enthält keine Zeilen.']);
        }

        return $csv;
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function mapping(Request $request, array $headers): array
    {
        return $request->has('mapping')
            ? array_map(fn (?string $field) => (string) $field, array_values($request->input('mapping')))
            : ContactCsv::suggest($headers);
    }
}
