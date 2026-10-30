<?php

namespace App\Integration\Http\Controllers;

use App\Catalog\Models\Package;
use App\Crm\Models\ClientCase;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Models\User;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Actions\CreateQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Actions\ReviseQuote;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Actions\UpdateQuote;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Support\SilentWrite;
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
    public function store(
        Request $request,
        ClientCase $case,
        UpdateQuote $save,
        CreateQuote $create,
    ): JsonResponse {
        $validated = $request->validate([
            ...UpdateQuote::rules(),
            'package' => ['nullable', 'string', Rule::exists('packages', 'public_id')->where('is_active', true)],
        ]);

        $quote = $create(
            $case,
            actor: $this->actor($request),
            package: isset($validated['package']) ? Package::findActive($validated['package']) : null,
        );

        $save($quote, $this->withMode([
            ...$this->current($quote->fresh(['items', 'paymentStages'])),
            ...Arr::except($validated, 'package'),
            'valid_until' => $validated['valid_until'] ?? now()->addDays(StudioSettings::current()->quote_validity_days)->toDateString(),
        ]));

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

        $save($quote, $this->withMode([...$this->current($quote), ...$validated]));

        return $this->answer($quote);
    }

    public function send(Request $request, Quote $quote, SendQuote $send, MarkQuoteSent $mark): JsonResponse
    {
        $validated = $request->validate(['sent_at' => ['nullable', 'date', 'before_or_equal:now']]);
        $at = $this->date($validated['sent_at'] ?? null);

        // A silent write never left the app, so it goes out over the
        // studio's own channel, not the client mail SendQuote fires.
        $this->attempt(fn () => SilentWrite::active()
            ? $mark($quote, 'self', $this->actor($request), $at)
            : $send($quote, $this->actor($request), $at));

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
            'items' => ['array'],
            'items.*' => ['string'],
        ]);

        $contact = isset($validated['contact'])
            ? $quote->case->contacts()->where('contacts.public_id', $validated['contact'])->first()
            : $quote->case->primaryContact();

        if ($contact === null) {
            throw ValidationException::withMessages(['contact' => 'The project has no such contact to accept on behalf of.']);
        }

        // A repeat call carrying different values is a correction, not a
        // duplicate — AcceptQuote's own idempotent early return only covers
        // an identical retry, so a changed hours or contact must be refused
        // rather than silently ignored.
        if ($quote->status === QuoteStatus::Accepted
            && ($contact->id !== $quote->accepted_by_contact_id || ($validated['hours'] ?? $quote->hours) !== $quote->hours)) {
            throw ValidationException::withMessages([
                'quote' => "Quote {$quote->public_id} is already accepted and cannot be re-accepted with different values.",
            ]);
        }

        $accept(
            $quote,
            $contact,
            null,
            $validated['hours'] ?? null,
            $validated['items'] ?? [],
            at: $this->date($validated['accepted_at'] ?? null),
        );

        return $this->answer($quote);
    }

    /** @return array<string, mixed> the quote as UpdateQuote::rules() would receive it */
    private function current(Quote $quote): array
    {
        return [
            ...$quote->only(['intro', 'outro', 'discount_value', 'hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours', 'time_title', 'time_description', 'time_sort_order']),
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

    /**
     * A caller that sends a rate without a mode means the slider, as a
     * package with a rate does (SeedQuoteFromPackage). A rate left on a
     * classic quote renders a slider in PDF and portal the editor never shows.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withMode(array $data): array
    {
        $data['pricing_mode'] ??= ($data['hourly_rate'] ?? null) === null ? PricingMode::Classic->value : PricingMode::Choice->value;

        return $data;
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
