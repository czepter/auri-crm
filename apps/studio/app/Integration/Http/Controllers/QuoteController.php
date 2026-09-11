<?php

namespace App\Integration\Http\Controllers;

use App\Catalog\Data\PackageData;
use App\Catalog\Models\Package;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Actions\ReviseQuote;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Actions\UpdateQuote;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The quote editor on the api surface, through the same UpdateQuote,
 * SendQuote, ReviseQuote and AcceptQuote the board and the portal use.
 *
 * update() is a merge, unlike the board's wholesale replace: a caller that
 * sends only `items` must not wipe the payment stages or the slider a screen
 * set up.
 */
class QuoteController extends Controller
{
    /** What create_quote's `package` can start from: the active ones, as the board's picker offers them. */
    public function packages(): JsonResponse
    {
        $packages = Package::active()->with('items')->orderBy('name')->get();

        return response()->json(['data' => $packages->map(PackageData::fromModel(...))->all()]);
    }

    public function store(Request $request, Project $project, UpdateQuote $save, SeedQuoteFromPackage $seed, StudioSettingsResolver $settings): JsonResponse
    {
        $validated = $request->validate([
            ...UpdateQuote::rules(),
            'package' => ['nullable', 'string', Rule::exists('packages', 'public_id')->where('is_active', true)],
        ]);

        $quote = $project->quotes()->create();

        if (isset($validated['package'])) {
            $seed($quote, Package::active()->where('public_id', $validated['package'])->firstOrFail());
        }

        $save($quote, [
            ...$this->current($quote->fresh(['items', 'paymentStages'])),
            ...Arr::except($validated, 'package'),
            'valid_until' => $validated['valid_until'] ?? now()->addDays($settings->quoteValidityDays())->toDateString(),
        ]);

        AuditLogger::record(
            event: 'quote.created',
            actor: $this->actor($request),
            context: ['project' => $project->public_id],
            subject: $quote->public_id,
            projectId: $project->id,
        );

        return $this->answer($quote, 201);
    }

    public function update(Request $request, Quote $quote, UpdateQuote $save): JsonResponse
    {
        $validated = $request->validate(UpdateQuote::rules());

        if ($quote->status !== QuoteStatus::Draft) {
            throw ValidationException::withMessages([
                'quote' => "Quote {$quote->public_id} is {$quote->status->value} and cannot be changed. Revise it instead.",
            ]);
        }

        $save($quote, [...$this->current($quote), ...$validated]);

        return $this->answer($quote);
    }

    public function send(Request $request, Quote $quote, SendQuote $send): JsonResponse
    {
        $validated = $request->validate(['sent_at' => ['nullable', 'date', 'before_or_equal:now']]);

        $this->attempt(fn () => $send($quote, $this->actor($request), $this->date($validated['sent_at'] ?? null)));

        return $this->answer($quote);
    }

    public function revise(Request $request, Quote $quote, ReviseQuote $revise): JsonResponse
    {
        return $this->answer($this->attempt(fn () => $revise($quote, $this->actor($request))), 201);
    }

    /** A yes given by mail or on the phone, recorded by staff for the client. */
    public function accept(Request $request, Quote $quote, AcceptQuote $accept): JsonResponse
    {
        $validated = $request->validate([
            'contact' => ['nullable', 'string'],
            'accepted_at' => ['nullable', 'date', 'before_or_equal:now'],
            'hours' => ['nullable', 'integer', 'min:0'],
        ]);

        $contact = isset($validated['contact'])
            ? $quote->project->contacts()->where('contacts.public_id', $validated['contact'])->first()
            : $quote->project->primaryContact();

        if ($contact === null) {
            throw ValidationException::withMessages(['contact' => 'The project has no such contact to accept on behalf of.']);
        }

        $accept($quote, $contact, null, $validated['hours'] ?? null, at: $this->date($validated['accepted_at'] ?? null));

        return $this->answer($quote);
    }

    /** @return array<string, mixed> the quote as UpdateQuote::rules() would receive it */
    private function current(Quote $quote): array
    {
        return [
            ...$quote->only(['intro', 'outro', 'discount_value', 'hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours']),
            'valid_until' => $quote->valid_until?->toDateString(),
            'discount_type' => $quote->discount_type?->value,
            'items' => $quote->items->map(fn (QuoteItem $item) => [
                ...$item->only(['title', 'description', 'quantity', 'unit_price', 'discount_value', 'selectable', 'selected', 'outcome']),
                'discount_type' => $item->discount_type?->value,
                'id' => $item->public_id,
            ])->all(),
            'stages' => $quote->paymentStages->map->only(['percent', 'label', 'minimum'])->all(),
        ];
    }

    private function attempt(callable $act): mixed
    {
        try {
            return $act();
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['quote' => $e->getMessage()]);
        }
    }

    private function answer(Quote $quote, int $status = 200): JsonResponse
    {
        return response()->json(['data' => QuoteData::fromModel($quote->fresh(['items', 'paymentStages', 'predecessor']))], $status);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
