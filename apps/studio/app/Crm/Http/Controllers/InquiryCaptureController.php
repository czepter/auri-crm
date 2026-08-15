<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\HasLabel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InquiryCaptureController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', Lead::class);

        return Inertia::render('Leads/Create', [
            'types' => self::options(ProjectType::cases()),
            'sources' => self::options(LeadSource::cases()),
            // C-08: not cases(). The web-form channel belongs to the public
            // endpoint alone — offering it here would make "how many
            // inquiries did the website bring us" unanswerable the first
            // time someone picked it by hand for a phone call.
            'channels' => self::options(CaptureChannel::staffSelectable()),
        ]);
    }

    public function store(CaptureInquiryData $data, CaptureInquiry $capture): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $capture($data, auth('staff')->user());

        return redirect('/anfragen');
    }

    /**
     * @param  array<int, BackedEnum&HasLabel>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private static function options(array $cases): array
    {
        return array_map(
            fn (BackedEnum&HasLabel $case) => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
