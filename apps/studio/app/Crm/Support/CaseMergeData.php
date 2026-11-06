<?php

namespace App\Crm\Support;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Contracts\MergeDataProvider;
use App\Documents\Support\DocumentMergeData;
use Illuminate\Database\Eloquent\Model;

class CaseMergeData implements MergeDataProvider
{
    /**
     * @param  ClientCase|null  $case
     * @return array<string, array<string, string>>
     */
    public function blocks(?Model $case): array
    {
        return [
            'project' => [
                'name' => $case?->name ?? '',
                'type' => $case?->type->name ?? '',
                'bookedAt' => $case?->booked_at?->format('d.m.Y') ?? '',
            ],
            'contact' => self::contact($case?->primaryContact()),
            // The occasion this document is about. Same primary shoot the
            // invoice's Leistungszeitraum reads, named plainly here because a
            // quote and a contract both talk about the day rather than about
            // a service period.
            'shoot' => [
                'date' => $case?->primaryShoot?->date?->format('d.m.Y') ?? '',
                // Every uncancelled shoot by date, so a contract names the days it books.
                'list' => $case === null ? '' : $case->shoots
                    ->whereNull('cancelled_at')
                    ->sortBy(fn ($shoot) => $shoot->date?->format('Y-m-d') ?? '9999')
                    ->map(fn ($shoot) => trim(($shoot->date?->format('d.m.Y') ?? '').' '.($shoot->venue() ? '('.$shoot->venue().')' : '')))
                    ->filter()
                    ->implode('; '),
            ],
            // From the payment plan, so a contract cannot name a date the
            // plan does not carry. Empty until the project is booked.
            'payment' => self::payment($case),
        ];
    }

    /** @return array<string, string> */
    private static function payment(?ClientCase $case): array
    {
        $installments = $case?->paymentPlan?->installments->sortBy('sort_order') ?? collect();

        return [
            'depositDue' => $installments->first()?->due_on?->format('d.m.Y') ?? '',
            'plan' => $installments
                ->map(fn ($i) => ($i->title ?: 'Zahlung').' '.DocumentMergeData::money($i->amount).' bis '.$i->due_on->format('d.m.Y'))
                ->implode('; '),
        ];
    }

    /** @return array<string, string> */
    public static function contact(?Contact $contact): array
    {
        return [
            'name' => $contact?->name ?? '',
            'email' => $contact?->email ?? '',
            'street' => $contact?->street ?? '',
            'postcode' => $contact?->postcode ?? '',
            'city' => $contact?->city ?? '',
        ];
    }
}
