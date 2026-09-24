<?php

namespace App\Portal\Actions;

use App\Catalog\Models\Package;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ein Bestandskunde fragt eine Leistung an. Dritter Weg, auf dem ein Vorgang
 * entsteht — neben der Erfassung von Hand und I-03 — und der einzige, auf dem
 * der Kunde selbst schreibt.
 *
 * Eigene Action statt CaptureInquiry mit anderen Parametern, aus drei Gründen,
 * die alle daran hängen, dass hier kein Fremder anfragt:
 *
 *  - Der Kontakt steht fest. Keine Auflösung über E-Mail, keine weiteren
 *    Personen, keine Projektfelder — was das Studio über ihn weiß, steht
 *    schon in seiner Zeile.
 *  - LeadSource beantwortet „woher kennt uns der Kunde". Bei einem
 *    Bestandskunden ist das an seiner ersten Anfrage beantwortet und wird von
 *    dort übernommen, statt die Liste um einen Fall zu erweitern, der die
 *    Zählbarkeit der übrigen verwässert. Dass die Anfrage aus dem Portal kam,
 *    steht in captured_via.
 *  - Nicht jede Leistung hat einen Termin. Ein Album ist kein Shooting, und
 *    CaptureInquiry legt immer genau einen an. Hier entsteht ein Shoot nur,
 *    wenn das Paket einen Wunschtermin erfragt.
 *
 * Eine Transaktion, aus demselben Grund wie dort: ein halb angelegter Vorgang
 * ist schlimmer als ein abgelehnter, weil niemand nach ihm suchen würde.
 */
class RequestService
{
    public function __construct(private readonly CrmVocabulary $stages) {}

    public function __invoke(
        Contact $contact,
        Package $package,
        ?string $date,
        ?string $venue,
        ?string $text,
    ): ClientCase {
        return DB::transaction(function () use ($contact, $package, $date, $venue, $text) {
            $now = now();

            $project = ClientCase::create([
                'name' => $package->name,
                'type' => $package->project_type_key,
                // Sichtbar, ohne die Vorgabe des Kontakts zu fragen: der Kunde
                // hat diesen Vorgang selbst ausgelöst und wird gleich darauf
                // umgeleitet. Ein Vorgang, den er nicht sehen darf, wäre die
                // Antwort auf seinen eigenen Klick.
                'portal_visible' => true,
            ]);

            $project->attachContact($contact, CaseContactRole::Primary);

            $lead = Lead::create([
                'case_id' => $project->id,
                'package_id' => $package->id,
                'source' => $this->sourceFor($contact),
                'stage' => $this->stages->anchored(StagePipeline::Lead, StageAnchor::Entry),
                'stage_entered_at' => $now,
                'inquiry_text' => $text,
                'captured_via' => CaptureChannel::Portal,
                'captured_at' => $now,
            ]);

            if ($package->portal_requires_date) {
                Shoot::create([
                    'case_id' => $project->id,
                    'kind' => Shoot::kindFor($package->project_type_key === null ? null : $this->stages->type($package->project_type_key)),
                    'date' => $date,
                    'venue_name' => $venue,
                    'status' => $this->stages->anchored(StagePipeline::Project, StageAnchor::Entry),
                    'status_entered_at' => $now,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }

            AuditLogger::record(
                event: 'lead.captured',
                actor: null,
                context: ['via' => CaptureChannel::Portal->value, 'package' => $package->public_id],
                subject: $project->public_id,
                projectId: $project->id,
            );

            LeadCaptured::dispatch($lead);

            return $project;
        });
    }

    /**
     * Die Herkunft der ersten Anfrage dieses Kontakts. Ein Bestandskunde hat
     * uns nicht zweimal anders kennengelernt, und eine zweite Zeile mit
     * „Bestandskunde" im Trichter würde die Frage, die LeadSource beantwortet,
     * nur unbeantwortbar machen.
     */
    private function sourceFor(Contact $contact): LeadSource
    {
        $first = Lead::query()
            ->whereIn('case_id', $contact->cases()->select('cases.id'))
            ->orderBy('captured_at')
            ->value('source');

        return $first instanceof LeadSource ? $first : (LeadSource::tryFrom((string) $first) ?? LeadSource::Other);
    }
}
