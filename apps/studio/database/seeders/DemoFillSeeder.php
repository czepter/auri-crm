<?php

namespace Database\Seeders;

use App\Catalog\Actions\SavePackage;
use App\Catalog\Enums\PortalPriceMode;
use App\Catalog\Models\Package;
use App\Contracts\Enums\ContractState;
use App\Contracts\Models\Contract;
use App\Crm\Actions\SeedFormInstanceFromTemplate;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\FormTemplate;
use App\Crm\Support\CrmVocabulary;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Engagement\Models\Thread;
use App\Enums\AbsenceCategory;
use App\Gallery\Models\GalleryPreset;
use App\Models\Absence;
use App\Models\StudioClosure;
use App\Models\StudioSettings;
use App\Models\User;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentPlan;
use Carbon\Carbon;
use App\Quotes\Actions\CreateQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Actions\UpdateStudioSetting;
use App\Support\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills an already seeded DemoCrmSeeder database with the content the
 * screens around the pipeline need: packages, templates, legal texts,
 * documents, money, messages. Every block checks for what it writes first,
 * so it can run against a live dev database any number of times.
 */
class DemoFillSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'christian@nordlicht.test')->first() ?? User::query()->firstOrFail();
        $meier = ClientCase::query()->where('name', 'Meier & Kowalski')->firstOrFail();

        $this->seedStudioContact($actor);
        $this->seedPackages();
        $legal = $this->seedLegalTexts($actor);
        $contract = $this->seedContractTemplate();
        $questionnaire = $this->seedQuestionnaireTemplate();
        $this->seedGalleryPreset();

        $this->seedContract($meier, $contract, $actor);
        $this->seedQuote($meier, $actor);
        $this->seedQuestionnaire($meier, $questionnaire, $actor);
        $this->seedPaymentPlan($meier, $actor);
        $this->seedStageQuotes($actor);
        $this->seedSentQuoteNumber();
        $this->seedCreditNote($actor);
        $this->seedAbsenceAndClosure($actor);
        $this->seedThreads($meier, $actor);
        $this->repairDemoDates($meier);
    }

    private function repairDemoDates(ClientCase $meier): void
    {
        $meier->galleries()->where('title', 'like', 'Meier & Kowalski%Hochzeit')->update(['title' => 'Verlobungsshooting']);
        $meier->shoots()->where('label', 'After-Wedding-Shooting')->update(['date' => '2027-06-26']);

        $anni = ClientCase::query()->where('name', 'like', 'Annika Reuter%')->first();
        $anni?->shoots()->where('is_primary', true)->where('date', '<', '2027-01-01')->update(['date' => '2027-05-29']);

        $primary = $meier->primaryContact();
        if ($primary !== null && $primary->name === 'Meier & Kowalski') {
            $primary->update(['name' => 'Lena Meier']);
            $meier->attachContact(Contact::create(['name' => 'Jonas Kowalski']), CaseContactRole::Partner);
        }
    }

    private function seedStudioContact(User $actor): void
    {
        $settings = app(UpdateStudioSetting::class);

        foreach ([
            'postcode' => '22765',
            'city' => 'Hamburg',
            'timezone' => 'Europe/Berlin',
            'accent_color' => '#1C6870',
            'mail_from_name' => 'Nordlicht Fotografie',
            'mail_from_address' => 'hallo@nordlicht-fotografie.test',
            'portal_contact_email' => 'hallo@nordlicht-fotografie.test',
            'portal_contact_phone' => '+49 40 5550100',
            'bank_account_holder' => 'Nordlicht Fotografie GmbH',
            'bank_iban' => 'DE89370400440532013000',
            'bank_bic' => 'COBADEFFXXX',
            'portal_sign_contracts_enabled' => true,
        ] as $key => $value) {
            if (StudioSettings::current()->{$key} !== $value) {
                $settings($key, $value, $actor);
            }
        }
    }

    private function seedPackages(): void
    {
        $save = app(SavePackage::class);

        foreach ([
            ['Hochzeit Klassik', 'Reportage vom Getting-Ready bis zum Eröffnungstanz.', 'wedding', true, PortalPriceMode::From, [
                ['Hochzeitsreportage Ganztag', 'bis 10 Std. am Hochzeitstag, Bearbeitung, Online-Galerie', 1, 350000],
                ['Bildauswahl und Retusche', null, 1, 0],
            ]],
            ['Hochzeit Komplett', 'Verlobungsshooting und Hochzeitstag mit zweitem Fotografen.', 'wedding', false, null, [
                ['Hochzeitsreportage Ganztag', 'bis 12 Std. am Hochzeitstag, zwei Fotografen', 1, 520000],
                ['Verlobungsshooting', '2 Std., Ort nach Wahl', 1, 45000],
                ['Fotobuch 30 x 30 cm', '30 Seiten, Leineneinband', 1, 38000],
            ]],
            ['Familienportrait', 'Eine Stunde Shooting, 25 bearbeitete Bilder.', 'portrait', false, null, [
                ['Portraitshooting', '1 Std., Studio oder Außenlocation', 1, 29000],
            ]],
        ] as [$name, $description, $type, $offered, $mode, $items]) {
            if (Package::query()->where('name', $name)->exists()) {
                continue;
            }

            $save(new Package, [
                'name' => $name,
                'description' => $description,
                'project_type_key' => $type,
                'portal_offered' => $offered,
                ...($mode ? ['portal_price_mode' => $mode] : []),
                'items' => collect($items)->map(fn (array $i) => [
                    'title' => $i[0], 'description' => $i[1], 'quantity' => $i[2], 'unit_price' => $i[3],
                ])->all(),
            ]);
        }
    }

    /** @return array{agb: DocumentTemplate, widerruf: DocumentTemplate} */
    private function seedLegalTexts(User $actor): array
    {
        $texts = [
            'agb' => $this->template(DocumentTemplateType::Legal, 'Allgemeine Geschäftsbedingungen',
                '<h2>1. Geltung</h2><p>Diese Bedingungen gelten für alle Aufträge zwischen {{ studio.name }} und dem Auftraggeber.</p>'
                .'<h2>2. Vertragsschluss</h2><p>Der Vertrag kommt mit der Annahme des Angebots zustande. Der Termin ist erst mit der vereinbarten Anzahlung reserviert.</p>'
                .'<h2>3. Nutzungsrechte</h2><p>Der Auftraggeber erhält ein einfaches Nutzungsrecht für private Zwecke. Veröffentlichungen im Internet sind mit Nennung des Fotografen gestattet.</p>'
                .'<h2>4. Lieferung</h2><p>Die bearbeiteten Bilder werden in einer Online-Galerie bereitgestellt. Die Lieferzeit beträgt in der Regel acht Wochen.</p>'),
            'widerruf' => $this->template(DocumentTemplateType::Legal, 'Widerrufsbelehrung',
                '<h2>Widerrufsrecht</h2><p>Verbraucher können ihre Vertragserklärung innerhalb von vierzehn Tagen ohne Angabe von Gründen in Textform widerrufen. Die Frist beginnt mit Erhalt dieser Belehrung.</p>'
                .'<p>Der Widerruf ist zu richten an {{ studio.name }}.</p>'
                .'<h2>Folgen des Widerrufs</h2><p>Im Fall eines wirksamen Widerrufs sind die beiderseits empfangenen Leistungen zurückzugewähren.</p>'),
        ];

        $settings = app(UpdateStudioSetting::class);

        foreach ($texts as $key => $template) {
            if (StudioSettings::current()->{"portal_legal_{$key}_family"} !== $template->family_id) {
                $settings("portal_legal_{$key}_family", $template->family_id, $actor);
            }
        }

        return $texts;
    }

    private function seedContractTemplate(): DocumentTemplate
    {
        $template = $this->template(DocumentTemplateType::Contract, 'Hochzeitsvertrag',
            '<h2>Fotografievertrag</h2>'
            .'<p>Zwischen {{ studio.name }} und {{ contact.name }} wird für das Projekt {{ project.name }} folgender Vertrag geschlossen.</p>'
            .'<h2>Leistung</h2><p>Fotografische Begleitung des Hochzeitstages laut Angebot, Bildbearbeitung und Lieferung in einer Online-Galerie.</p>'
            .'<h2>Vergütung</h2><p>Die Vergütung richtet sich nach dem angenommenen Angebot. Die Anzahlung richtet sich nach dem Zahlungsplan.</p>'
            .'<h2>Ausfall</h2><p>Fällt der Fotograf krankheitsbedingt aus, stellt das Studio nach Möglichkeit einen gleichwertigen Ersatz.</p>');

        if (str_contains($template->body, 'fällig mit Vertragsschluss')) {
            $template->update(['body' => str_replace('Die Anzahlung ist fällig mit Vertragsschluss.', 'Die Anzahlung richtet sich nach dem Zahlungsplan.', $template->body)]);
        }

        return $template;
    }

    private function template(DocumentTemplateType $type, string $name, string $body): DocumentTemplate
    {
        return DocumentTemplate::query()->current()->ofType($type)->where('name', $name)->first()
            ?? DocumentTemplate::createFirstVersion(['type' => $type, 'name' => $name, 'body' => $body]);
    }

    private function seedQuestionnaireTemplate(): FormTemplate
    {
        $template = FormTemplate::query()->where('name', 'Hochzeitsfragebogen')->first();

        if ($template !== null) {
            return $template;
        }

        $template = FormTemplate::create(['name' => 'Hochzeitsfragebogen', 'project_type' => 'wedding']);

        foreach ([
            ['Wer gehört zur engsten Familie?', 'text', true, null],
            ['Gibt es Gäste, die nicht fotografiert werden möchten?', 'text', false, null],
            ['Uhrzeit der Trauung', 'time', true, null],
            ['Wie soll der Tag festgehalten werden?', 'select', false, ['Reportage', 'Gemischt', 'Gestellt']],
        ] as $index => [$label, $type, $required, $options]) {
            $template->fields()->create(['label' => $label, 'type' => $type, 'required' => $required, 'options' => $options, 'sort_order' => $index]);
        }

        return $template;
    }

    private function seedGalleryPreset(): void
    {
        GalleryPreset::query()->firstOrCreate(['name' => 'Hochzeit Standard'], [
            'design' => GalleryPreset::DESIGN,
            'expiry_mode' => 'days',
            'expiry_days' => 180,
            'downloads_enabled' => true,
            'lists_enabled' => true,
            'comments_enabled' => true,
        ]);
    }

    private function seedContract(ClientCase $project, DocumentTemplate $template, User $actor): void
    {
        $contract = Contract::query()->where('case_id', $project->id)->latest('id')->first();

        if ($contract === null) {
            $contract = Contract::create([
                'case_id' => $project->id,
                'contract_template_id' => $template->id,
                'requested_by_id' => $actor->id,
                'state' => ContractState::Draft,
                'version' => 1,
            ]);
        }

        if ($contract->state === ContractState::Valid && $contract->sent_at !== null) {
            return;
        }

        $contract->markSent();

        AuditLogger::record(
            event: 'contract.sent',
            actor: $actor,
            context: ['version' => 1],
            subject: $contract->public_id,
            projectId: $project->id,
        );
    }

    private function seedQuote(ClientCase $project, User $actor): void
    {
        $quote = Quote::query()->where('case_id', $project->id)->latest('id')->first();

        if ($quote === null) {
            $quote = app(CreateQuote::class)($project, $actor);
            $quote->items()->create(['title' => 'Hochzeitsreportage Ganztag', 'description' => 'bis 10 Std. am Hochzeitstag, Bearbeitung, Online-Galerie', 'quantity' => 1, 'unit_price' => 350000, 'sort_order' => 0]);
            $quote->items()->create(['title' => 'Verlobungsshooting', 'description' => '2 Std., Ort nach Wahl', 'quantity' => 1, 'unit_price' => 45000, 'sort_order' => 1]);
            $quote->recalculateTotal();
        }

        if ($quote->status === QuoteStatus::Draft) {
            app(MarkQuoteSent::class)($quote->refresh(), 'mail', $actor);
        }

        $quote->refresh();

        if ($quote->status === QuoteStatus::Sent && $project->booked_at !== null) {
            app(\App\Quotes\Actions\AcceptQuote::class)($quote, $project->primaryContact(), null, at: Carbon::parse('2026-11-05 14:00'));
            $quote->refresh();
        }

        $this->alignQuote($project, $quote);
    }

    /**
     * Meier & Kowalski's quote is both lines (3.950 € net, 4.700,50 € gross),
     * sent 03.11. and accepted 05.11.2026, with its 30/70 stages. A sent or
     * accepted quote is immutable at model level, so totals and dates are
     * repaired with plain queries.
     */
    private function alignQuote(ClientCase $project, Quote $quote): void
    {
        $quote->items()->firstOrCreate(
            ['title' => 'Verlobungsshooting'],
            ['description' => '2 Std., Ort nach Wahl', 'quantity' => 1, 'unit_price' => 45000, 'sort_order' => 1],
        );

        $items = $quote->items()->get();
        $totals = Quote::computeTotals($items, null, null);

        Quote::query()->whereKey($quote->id)->update([
            ...$totals,
            ...Quote::computeVat($totals['net'], $items),
            ...($quote->status === QuoteStatus::Accepted ? [
                'sent_at' => Carbon::parse('2026-11-03 10:00'),
                'accepted_at' => Carbon::parse('2026-11-05 14:00'),
            ] : []),
        ]);

        if ($quote->status === QuoteStatus::Accepted) {
            $stamp = Carbon::parse('2026-11-05 14:00');

            $project->lead()->update(['stage_entered_at' => $stamp]);
            ClientCase::query()->whereKey($project->id)->whereNotNull('booked_at')->update(['booked_at' => $stamp]);
            $project->shoots()->where('is_primary', true)->where('date', '<', '2027-01-01')->update(['date' => '2027-06-12']);
        }

        if ($quote->paymentStages()->doesntExist()) {
            $quote->paymentStages()->create(['percent' => 30, 'label' => 'Anzahlung', 'sort_order' => 0]);
            $quote->paymentStages()->create(['percent' => 70, 'label' => 'Restzahlung', 'sort_order' => 1]);
        }
    }

    /** Anzahlung 30 % due a week after the acceptance, the rest a week before the wedding. */
    private function seedPaymentPlan(ClientCase $project, User $actor): void
    {
        $quote = Quote::query()->where('case_id', $project->id)->where('status', QuoteStatus::Accepted)->latest('id')->firstOrFail()->refresh();
        $plan = PaymentPlan::query()->where('case_id', $project->id)->first();

        if ($plan !== null) {
            return;
        }

        $plan = app(CreatePaymentPlan::class)($project, $quote->gross(), ['2026-11-12', '2027-06-05']);

        $issue = app(IssueInvoice::class);
        $deposit = $issue($plan->installments()->orderBy('sort_order')->firstOrFail(), $actor);

        app(MarkInstallmentPaid::class)(
            $deposit->installments()->firstOrFail(), SettledVia::Transfer, '2026-11-06', 'Überweisung Anzahlung', $actor,
        );

        // The client's thread about it reads as written today.
        $thread = Thread::query()->where('case_id', $project->id)->where('subject', 'Rechnung Anzahlung')->first();

        foreach ($thread?->messages()->orderBy('id')->get() ?? [] as $index => $message) {
            $message->forceFill(['created_at' => now()->subHours(3 - $index)])->save();
        }
    }

    /** Every project at or beyond Angebot raus holds the quote the funnel counts it for. */
    private function seedStageQuotes(User $actor): void
    {
        $vocabulary = app(CrmVocabulary::class);
        $sentAt = $vocabulary->anchored(StagePipeline::Lead, StageAnchor::QuoteSent)?->sort_order;
        $acceptedAt = $vocabulary->anchored(StagePipeline::Lead, StageAnchor::QuoteAccepted)?->sort_order;

        if ($sentAt === null || $acceptedAt === null) {
            return;
        }

        foreach (ClientCase::query()->with('lead')->get() as $project) {
            $lead = $project->lead;

            if ($lead?->isLost()) {
                continue;
            }

            $order = $lead?->stage->sort_order ?? 0;
            $accepted = $project->booked_at !== null || $order >= $acceptedAt;

            if (! $accepted && $order < $sentAt) {
                continue;
            }

            $stamp = $lead?->stage_entered_at ?? $project->booked_at;
            $quote = $project->quotes()->latest('id')->first();

            if ($quote === null) {
                $quote = app(CreateQuote::class)($project, $actor);
                $quote->items()->create(['title' => 'Hochzeitsreportage Ganztag', 'description' => 'bis 10 Std. am Hochzeitstag, Bearbeitung, Online-Galerie', 'quantity' => 1, 'unit_price' => 350000, 'sort_order' => 0]);
                $quote->recalculateTotal();
            }

            if ($quote->status === QuoteStatus::Draft) {
                app(MarkQuoteSent::class)($quote->refresh(), 'mail', $actor, $accepted ? $stamp->copy()->subDay() : $stamp);
                $quote->refresh();
            }

            if ($accepted && $quote->status === QuoteStatus::Sent) {
                app(\App\Quotes\Actions\AcceptQuote::class)($quote, $project->primaryContact(), null, at: $stamp);
            }
        }
    }

    /** Annika's sent quote carries a number and a frozen VAT snapshot, whatever path created it. */
    private function seedSentQuoteNumber(): void
    {
        $project = ClientCase::query()->where('name', 'like', 'Annika Reuter%')->first();
        $quote = $project === null ? null : Quote::query()->where('case_id', $project->id)->latest('id')->first();

        if ($quote === null || $quote->status !== QuoteStatus::Sent) {
            return;
        }

        $patch = [];

        if ($quote->number === null) {
            $patch['number'] = (app(AllocateDocumentNumber::class)(documentType: DocumentType::Quote))['number'];
        }

        if ($quote->vat_amount === null) {
            $patch += Quote::computeVat($quote->net, $quote->items()->get());
        }

        if ($patch !== []) {
            Quote::query()->whereKey($quote->id)->update($patch);
        }
    }

    private function seedQuestionnaire(ClientCase $project, FormTemplate $template, User $actor): void
    {
        if ($project->formInstances()->where('source_template_name', $template->name)->exists()) {
            return;
        }

        $instance = $project->formInstances()->create(['name' => $template->name, 'source_template_name' => $template->name]);
        app(SeedFormInstanceFromTemplate::class)($instance, $template->load('fields'));
        $instance->forceFill(['shared_at' => now()])->save();

        $contact = $project->primaryContact();
        $fields = $instance->fields()->get();

        $fields[0]->answer('Eltern beider Seiten, zwei Geschwister, die Großmutter', $contact);
        $fields[2]->answer('14:30', $contact);
    }

    private function seedCreditNote(User $actor): void
    {
        $project = ClientCase::query()->where('name', 'like', 'Sophie Lang%')->firstOrFail();

        if (Invoice::query()->where('case_id', $project->id)->exists()) {
            return;
        }

        $invoice = app(IssueInvoice::class)->positions($project->primaryContact(), $project, [
            ['title' => 'Anzahlung Hochzeitsreportage', 'quantity' => 1, 'unit_price' => 80000],
        ], now()->subDays(40)->toDateString(), [], $actor);

        $paid = app(MarkInstallmentPaid::class)(
            $invoice->installments()->firstOrFail(), SettledVia::Transfer, now()->subDays(35)->toDateString(), 'Überweisung Anzahlung', $actor,
        );

        app(IssueCreditNote::class)($invoice->refresh(), (int) $paid->amount / 2, 'Termin verschoben, halbe Anzahlung erstattet', $actor);
    }

    private function seedAbsenceAndClosure(User $actor): void
    {
        $nina = User::query()->where('email', 'nina@nordlicht.test')->first() ?? $actor;

        Absence::query()->firstOrCreate(
            ['user_id' => $nina->id, 'category' => AbsenceCategory::Holiday],
            ['starts_on' => now()->addDays(40)->toDateString(), 'ends_on' => now()->addDays(54)->toDateString()],
        );

        StudioClosure::query()->firstOrCreate(
            ['label' => 'Betriebsferien'],
            [
                'starts_on' => now()->addDays(90)->toDateString(),
                'ends_on' => now()->addDays(104)->toDateString(),
                'message' => 'Wir sind im Urlaub, Antworten kommen ab dem Ende der Betriebsferien.',
            ],
        );
    }

    private function seedThreads(ClientCase $meier, User $actor): void
    {
        $client = $meier->primaryContact();
        $sophie = ClientCase::query()->where('name', 'like', 'Sophie Lang%')->firstOrFail();

        // [project, contact, subject, [[author, body, days ago]]]. A thread ending on a
        // client line is what the inbox lists as unanswered.
        foreach ([
            [$meier, $client, 'Ablauf Hochzeitstag', [
                [$client, 'Hallo, wir haben den Ablauf angehängt. Passt die Zeit für die Gruppenfotos so?', 6],
                [$actor, 'Hallo, danke für den Ablauf. Die Gruppenfotos legen wir direkt nach der Trauung, dafür reichen 30 Minuten.', 5],
                [$client, 'Perfekt, dann bleibt es dabei.', 5],
                [$actor, 'Wunderbar, dann notiere ich das so.', 4],
            ]],
            [$meier, $client, 'Rechnung Anzahlung', [
                [$client, 'Die Anzahlung ist heute überwiesen, könnt ihr den Eingang bestätigen?', 0],
                [$actor, 'Eingang ist verbucht, vielen Dank.', 0],
            ]],
            [$meier, $client, 'Fotobuch', [
                [$actor, 'Beim Fotobuch gibt es Leinen in Sand und in Anthrazit. Welche Farbe darf es sein?', 3],
                [$client, 'Anthrazit gefällt uns besser.', 2],
                [$actor, 'Anthrazit ist notiert.', 2],
            ]],
            [$sophie, $sophie->primaryContact(), 'Terminverschiebung', [
                [$sophie->primaryContact(), 'Hallo, wir müssten den Hochzeitstermin eventuell verschieben. Habt ihr im Oktober noch ein Wochenende frei?', 0],
            ]],
        ] as [$project, $contact, $subject, $lines]) {
            if (Thread::query()->where('case_id', $project->id)->where('subject', $subject)->exists()) {
                continue;
            }

            DB::transaction(function () use ($project, $contact, $subject, $lines) {
                $thread = Thread::create(['case_id' => $project->id, 'subject' => $subject]);
                $thread->participants()->create(['contact_id' => $contact->id]);

                foreach ($lines as $index => [$author, $body, $daysAgo]) {
                    $thread->messages()->create([
                        ...$author instanceof Contact ? ['contact_id' => $author->id] : ['author_id' => $author->id],
                        'body' => $body,
                    ])->forceFill(['created_at' => now()->subDays($daysAgo)->addMinutes($index)])->save();
                }
            });
        }
    }
}
