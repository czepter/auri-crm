<?php

namespace Database\Seeders;

use App\Catalog\Actions\SavePackage;
use App\Catalog\Enums\PortalPriceMode;
use App\Catalog\Models\Package;
use App\Contracts\Enums\ContractState;
use App\Contracts\Models\Contract;
use App\Crm\Actions\SeedFormInstanceFromTemplate;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormTemplate;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Engagement\Models\Thread;
use App\Enums\AbsenceCategory;
use App\Gallery\Models\GalleryPreset;
use App\Models\Absence;
use App\Models\StudioClosure;
use App\Models\StudioSettings;
use App\Models\User;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\Invoice;
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
        $this->seedInvoices($meier, $actor);
        $this->seedCreditNote($actor);
        $this->seedAbsenceAndClosure($actor);
        $this->seedThreads($meier, $actor);
    }

    private function seedStudioContact(User $actor): void
    {
        $settings = app(UpdateStudioSetting::class);

        foreach ([
            'postcode' => '22765',
            'city' => 'Hamburg',
            'portal_contact_email' => 'hallo@nordlicht-fotografie.test',
            'portal_contact_phone' => '+49 40 5550100',
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
        return $this->template(DocumentTemplateType::Contract, 'Hochzeitsvertrag',
            '<h2>Fotografievertrag</h2>'
            .'<p>Zwischen {{ studio.name }} und {{ contact.name }} wird für das Projekt {{ project.name }} folgender Vertrag geschlossen.</p>'
            .'<h2>Leistung</h2><p>Fotografische Begleitung des Hochzeitstages laut Angebot, Bildbearbeitung und Lieferung in einer Online-Galerie.</p>'
            .'<h2>Vergütung</h2><p>Die Vergütung richtet sich nach dem angenommenen Angebot. Die Anzahlung ist fällig mit Vertragsschluss.</p>'
            .'<h2>Ausfall</h2><p>Fällt der Fotograf krankheitsbedingt aus, stellt das Studio nach Möglichkeit einen gleichwertigen Ersatz.</p>');
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
        if (Contract::query()->where('case_id', $project->id)->exists()) {
            return;
        }

        $contract = Contract::create([
            'case_id' => $project->id,
            'contract_template_id' => $template->id,
            'requested_by_id' => $actor->id,
            'state' => ContractState::Draft,
            'version' => 1,
        ]);

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

            return;
        }

        // A sent quote is immutable at model level, so a seed row that never
        // went through snapshotVat() is repaired with a plain query.
        if ($quote->vat_amount === null && $quote->status === QuoteStatus::Sent) {
            Quote::query()->whereKey($quote->id)->update(Quote::computeVat($quote->net, $quote->items()->get()));
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

    private function seedInvoices(ClientCase $project, User $actor): void
    {
        if (Invoice::query()->where('case_id', $project->id)->exists()) {
            return;
        }

        $issue = app(IssueInvoice::class);
        $contact = $project->primaryContact();

        $deposit = $issue->positions($contact, $project, [
            ['title' => 'Anzahlung Hochzeitsreportage', 'quantity' => 1, 'unit_price' => 100000],
        ], now()->subDays(30)->toDateString(), [], $actor);

        app(MarkInstallmentPaid::class)(
            $deposit->installments()->firstOrFail(), SettledVia::Transfer, now()->subDays(25)->toDateString(), 'Überweisung Anzahlung', $actor,
        );

        $issue->positions($contact, $project, [
            ['title' => 'Verlobungsshooting', 'quantity' => 1, 'unit_price' => 45000],
            ['title' => 'Fotobuch 30 x 30 cm', 'quantity' => 1, 'unit_price' => 38000],
        ], now()->addDays(14)->toDateString(), [], $actor);
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
                [$client, 'Die Anzahlung ist heute überwiesen, könnt ihr den Eingang bestätigen?', 25],
                [$actor, 'Eingang ist verbucht, vielen Dank.', 24],
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
