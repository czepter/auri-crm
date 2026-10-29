<?php

namespace App\Crm\Support;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Contracts\MergeDataProvider;
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
            ],
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
