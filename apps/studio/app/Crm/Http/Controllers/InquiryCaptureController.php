<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\HasLabel;
use App\Crm\Enums\LeadSource;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectType;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\StageSettingsPayload;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InquiryCaptureController extends Controller
{
    public function create(StudioSettingsResolver $settings): Response
    {
        $this->authorize('create', Lead::class);

        $types = app(CrmVocabulary::class)->types()
            ->filter(fn (ProjectType $type) => $type->is_active)
            ->load(['fields' => fn ($query) => $query->active()]);

        return Inertia::render('Leads/Create', [
            'types' => $types
                ->map(fn (ProjectType $type) => ['value' => $type->key, 'label' => $type->name])
                ->values()->all(),
            // C-25: die Felder aller aktiven Typen auf einmal, nach Typ
            // geschlüsselt, damit ein Typwechsel unter „Erweitert" sie ohne
            // zweite Anfrage tauscht.
            'fieldsByType' => $types
                ->mapWithKeys(fn (ProjectType $type) => [
                    $type->key => $type->fields
                        ->map(StageSettingsPayload::field(...))->values()->all(),
                ])
                ->all(),
            'sources' => self::options(LeadSource::cases()),
            // C-08: not cases(). The web-form channel belongs to the public
            // endpoint alone — offering it here would make "how many
            // inquiries did the website bring us" unanswerable the first
            // time someone picked it by hand for a phone call.
            'channels' => self::options(CaptureChannel::staffSelectable()),
            // Suggestions for the three "Sonstige" details, kept in the
            // settings so the studio's own recurring wording is one keystroke
            // rather than retyped at every call.
            'presets' => [
                'type' => $settings->leadPresets('type'),
                'source' => $settings->leadPresets('source'),
                'channel' => $settings->leadPresets('channel'),
            ],
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
