<?php

namespace Database\Seeders;

use App\Crm\Models\ClientCase;
use App\Finance\Actions\ChangeFiscalYearVatMode;
use App\Finance\Actions\DisposeFixedAsset;
use App\Finance\Actions\LockFiscalYear;
use App\Finance\Actions\SaveExpense;
use App\Finance\Actions\SetExpenseStatus;
use App\Finance\Actions\StoreFinanceDocument;
use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FixedAsset;
use App\Models\StudioSettings;
use App\Models\User;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\Invoice;
use App\Settings\Actions\UpdateStudioSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Three years of books for the Finanzen screens: Y-2 open with a draft left
 * over, Y-1 locked as a Kleinunternehmer year, Y current with every state the
 * screens have to show. Built through the actions, so it keeps proving them.
 */
class DemoFinanceSeeder extends Seeder
{
    private int $year;

    private ?User $actor;

    public function run(): void
    {
        if (app()->isProduction() || Expense::query()->exists()) {
            return;
        }

        $this->year = now()->year;
        $this->actor = User::query()->first();

        if (blank(StudioSettings::current()->tax_number)) {
            app(UpdateStudioSetting::class)('tax_number', '22/123/45678', $this->actor);
        }

        $this->income();
        $this->yearTwoAgo();
        $this->lastYear();
        $this->thisYear();
    }

    private function income(): void
    {
        $y = $this->year;
        $lang = $this->project('Sophie Lang');
        $ahrens = $this->project('Marie Ahrens');

        $this->invoice($lang, $this->day($y - 2, 9, 20), 100000, $this->day($y - 2, 10, 8));

        $first = $this->invoice($ahrens, $this->day($y - 1, 8, 28), 300000, $this->day($y - 1, 9, 12));
        $this->invoice($lang, $this->day($y - 1, 10, 25), 80000, $this->day($y - 1, 11, 14));
        $this->creditNote($first, 23800, $this->day($y - 1, 12, 5));

        $first = $this->invoice($ahrens, $this->day($y, 2, 24), 250000, $this->day($y, 3, 10));
        $this->creditNote($first, 59500, $this->day($y, 5, 6));
        $this->invoice($lang, $this->day($y, 5, 28), 180000, $this->day($y, 6, 12));
        $this->invoice($lang, $this->day($y, 10, 6), 120000, null);
    }

    private function yearTwoAgo(): void
    {
        $y = $this->year - 2;

        $this->expense(['vendor' => 'Nordhost Hosting GmbH', 'description' => 'Webhosting Jahrespaket', 'gross' => 11900, 'paid_at' => $this->day($y, 2, 10), 'category' => 'hosting_domains'], 'beleg', "y2-{$y}", true);
        $this->expense(['vendor' => 'Lichtwerk Software Ltd.', 'description' => 'Bildbearbeitung Abo', 'gross' => 2380, 'invoice_date' => $this->day($y, 11, 3), 'category' => 'software_abos']);
    }

    private function lastYear(): void
    {
        $y = $this->year - 1;
        app(ChangeFiscalYearVatMode::class)($y, 'small_business', $this->actor);

        $this->expense(['vendor' => 'Nordhost Hosting GmbH', 'description' => 'Webhosting Jahrespaket', 'gross' => 11900, 'paid_at' => $this->day($y, 2, 10), 'category' => 'hosting_domains'], 'beleg', 'y1-hosting', true);
        $this->expense(['vendor' => 'Lichtwerk Software Ltd.', 'description' => 'Bildbearbeitung Abo', 'gross' => 23800, 'paid_at' => $this->day($y, 4, 2), 'category' => 'software_abos'], 'beleg', 'y1-software', true);
        $this->expense(['vendor' => 'Fotokurs Hamburg Akademie', 'description' => 'Workshop Lichtführung', 'gross' => 30000, 'vat_rate' => 0, 'paid_at' => $this->day($y, 6, 18), 'category' => 'weiterbildung'], 'beleg', 'y1-kurs', true);
        $this->expense(
            ['vendor' => 'Kamerahaus Elbe', 'description' => 'Kamera-Body', 'gross' => 285600, 'paid_at' => $this->day($y, 9, 8), 'category' => 'fotozubehoer'],
            'beleg', 'y1-kamera', true,
            ['name' => 'Kamera-Body', 'acquired_at' => $this->day($y, 9, 8), 'cost_net' => 240000, 'cost_gross' => 285600, 'method' => 'linear', 'useful_life_months' => 36],
        );
        $this->expense(
            ['vendor' => 'Kamerahaus Elbe', 'description' => 'Blitzanlage', 'gross' => 107100, 'paid_at' => $this->day($y, 3, 14), 'category' => 'fotozubehoer'],
            'beleg', 'y1-blitz', true,
            ['name' => 'Blitzanlage', 'acquired_at' => $this->day($y, 3, 14), 'cost_net' => 90000, 'cost_gross' => 107100, 'method' => 'linear', 'useful_life_months' => 36],
        );

        // The PDF is rendered on first download; nothing here should reach Gotenberg.
        $queue = config('queue.default');
        config(['queue.connections.discard' => ['driver' => 'null'], 'queue.default' => 'discard']);

        try {
            app(LockFiscalYear::class)($y, $this->actor);
        } finally {
            config(['queue.default' => $queue]);
        }
    }

    private function thisYear(): void
    {
        $y = $this->year;
        $d = fn (int $month, int $day) => $this->day($y, $month, $day);

        $this->expense(['vendor' => 'Nordhost Hosting GmbH', 'description' => 'Webhosting Jahrespaket', 'gross' => 11900, 'paid_at' => $d(1, 15), 'category' => 'hosting_domains'], 'beleg', null, true);
        $this->expense(['vendor' => 'Lichtwerk Software Ltd.', 'description' => 'Bildbearbeitung Abo', 'invoice_no' => "LW-{$y}-041", 'invoice_date' => $d(2, 1), 'gross' => 2380, 'paid_at' => $d(2, 3), 'category' => 'software_abos'], 'beleg', 'e02', true);
        $this->expense(['vendor' => 'Pixelnetz Cloud Inc.', 'description' => 'Cloud-Speicher', 'currency' => 'USD', 'gross' => 4900, 'vat_rate' => 0, 'gross_eur' => 4512, 'paid_at' => $d(2, 20), 'category' => 'software_abos'], 'png', 'e03', true);
        $this->expense(['vendor' => 'Pixelnetz Cloud Inc.', 'description' => 'Cloud-Speicher', 'currency' => 'USD', 'gross' => 4900, 'vat_rate' => 0, 'paid_at' => $d(3, 20), 'category' => 'software_abos'], 'png', 'e04', true);
        $this->expense(['vendor' => 'Mobilfunk Nord AG', 'description' => 'Mobilfunk', 'gross' => 5950, 'business_share' => 50, 'paid_at' => $d(3, 5), 'category' => 'sonstiges'], 'beleg', 'e05', true);
        $this->expense(['vendor' => 'Papierhaus Elbe', 'description' => 'Fotodruckpapier', 'gross' => 3570, 'paid_at' => $d(4, 2), 'category' => 'fotozubehoer'], null, null, true);
        $this->expense(['vendor' => 'Nordbahn Reisen', 'description' => 'Bahnfahrt Hochzeit', 'gross' => 8900, 'vat_rate' => 700, 'paid_at' => $d(5, 12), 'category' => 'reise'], 'beleg', 'e07', true);
        $this->expense(['vendor' => 'Versicherungshaus Elbe', 'description' => 'Berufshaftpflicht', 'gross' => 31200, 'vat_rate' => 0, 'paid_at' => $d(1, 2), 'category' => 'versicherung'], 'beleg', 'e08', true);
        $this->expense(['vendor' => 'Fotokurs Hamburg Akademie', 'description' => 'Workshop Portrait', 'gross' => 45000, 'vat_rate' => 0, 'paid_at' => $d(6, 6), 'category' => 'weiterbildung'], 'beleg', 'e09', true);
        $this->expense(['vendor' => 'Privatverkäufer Kleinanzeige', 'description' => 'Stativ', 'gross' => 8000, 'vat_rate' => 0, 'paid_at' => $d(7, 1), 'category' => 'fotozubehoer', 'is_self_issued' => true], 'png', 'e10', true);
        $this->expense([
            'vendor' => 'Gesellschaft für Foto- und Medienbedarf, Vertrieb und Dienstleistungen im Großraum Hamburg und Umgebung mbH',
            'description' => str_repeat('Langer Beschreibungstext zur Prüfung der Tabellenzeile und des Formulars. ', 4),
            'gross' => 15000, 'business_share' => 0, 'paid_at' => $d(7, 15), 'category' => 'sonstiges',
            'note' => str_repeat('Interne Notiz mit viel Text, damit das Notizfeld mehrere Zeilen zeigt. ', 7),
        ], 'beleg', 'e11', true);

        $this->expense(['vendor' => 'Lichtwerk Software Ltd.', 'description' => 'Bildbearbeitung Abo', 'invoice_no' => "LW-{$y}-041", 'invoice_date' => $d(2, 1), 'gross' => 2380], 'beleg', 'e12', false, null, ['channel' => 'mail']);
        $this->expense(['vendor' => 'Bürobedarf Altona', 'description' => 'Ordner und Mappen', 'gross' => 4760, 'invoice_date' => $d(8, 18), 'category' => 'buero']);
        $ignored = $this->expense(['vendor' => 'Nordhost Hosting GmbH', 'description' => 'Doppelt abgebucht', 'gross' => 11900, 'paid_at' => $d(1, 16), 'category' => 'hosting_domains'], 'beleg', 'e14');
        app(SetExpenseStatus::class)($ignored, ExpenseStatus::Ignored, $this->actor);

        $this->expense(
            ['vendor' => 'Rechnerwerk Nord', 'description' => 'Mobiler Rechner', 'gross' => 172550, 'paid_at' => $d(4, 10), 'category' => 'buero'],
            'beleg', 'e15', true,
            ['name' => 'Mobiler Rechner', 'acquired_at' => $d(4, 10), 'cost_net' => 145000, 'cost_gross' => 172550, 'method' => 'linear', 'afa_category' => 'computer_software'],
        );
        $this->expense(
            ['vendor' => 'Kamerahaus Elbe', 'description' => 'Objektiv 85 mm', 'gross' => 76160, 'paid_at' => $d(5, 3), 'category' => 'fotozubehoer'],
            'beleg', 'e16', true,
            ['name' => 'Objektiv 85 mm', 'acquired_at' => $d(5, 3), 'cost_net' => 64000, 'cost_gross' => 76160, 'method' => 'gwg'],
        );
        $this->expense(
            ['vendor' => 'Kamerahaus Elbe', 'description' => 'Studioblitz-Set', 'gross' => 142800, 'invoice_date' => $d(10, 1)],
            null, null, false,
            ['name' => 'Studioblitz-Set', 'acquired_at' => $d(10, 1), 'cost_net' => 120000, 'cost_gross' => 142800, 'method' => 'linear', 'useful_life_months' => 36],
        );

        app(DisposeFixedAsset::class)(FixedAsset::query()->where('name', 'Blitzanlage')->sole(), $d(6, 30), 30000, $this->actor);
    }

    /**
     * @param  array<string, mixed>  $data  gross in cents; net and VAT follow from the rate (19 % unless given)
     * @param  'beleg'|'png'|null  $receipt
     * @param  array<string, mixed>|null  $asset
     */
    private function expense(array $data, ?string $receipt = null, ?string $variant = null, bool $book = false, ?array $asset = null, ?array $sourceRef = null): Expense
    {
        $categoryKey = $data['category'] ?? null;
        unset($data['category']);

        $payload = [
            'vat_rate' => 1900,
            'currency' => 'EUR',
            'business_share' => 100,
            ...$data,
            'category_id' => $categoryKey === null ? null : ExpenseCategory::query()->where('key', $categoryKey)->value('public_id'),
        ];

        if ($receipt !== null) {
            $document = $this->receipt($receipt, $variant);

            if ($sourceRef !== null) {
                $document->update(['source_ref' => $sourceRef]);
            }

            $payload['document_ids'] = [$document->public_id];
        }

        if ($asset !== null) {
            $payload['asset'] = $asset;
        }

        $expense = app(SaveExpense::class)(SaveExpense::validate($payload, $book), null, $this->actor);

        if ($book) {
            app(SetExpenseStatus::class)($expense, ExpenseStatus::Booked, $this->actor);
        }

        return $expense;
    }

    /** The first receipt keeps the fixture's own bytes, so dropping the file again finds it; later ones differ by a trailing comment. */
    private function receipt(string $fixture, ?string $variant): FinanceDocument
    {
        $name = $fixture === 'beleg' ? 'beleg.pdf' : 'beleg.png';
        $bytes = file_get_contents(database_path("seeders/fixtures/finance/$name"));
        $path = tempnam(sys_get_temp_dir(), 'beleg');
        file_put_contents($path, $variant === null ? $bytes : $bytes.($fixture === 'beleg' ? "%% $variant\n" : "\n$variant"));

        [$document] = app(StoreFinanceDocument::class)(new UploadedFile($path, $name, null, null, true), $this->actor);
        @unlink($path);

        return $document;
    }

    private function invoice(?ClientCase $project, string $issued, int $net, ?string $paidOn): Invoice
    {
        Carbon::setTestNow(CarbonImmutable::parse($issued)->setTime(10, 0));

        try {
            $invoice = app(IssueInvoice::class)->positions(
                $project?->primaryContact(),
                $project,
                [['title' => 'Fotografie', 'unit_price' => $net, 'vat_rate' => 1900]],
                $issued,
                ['issued_on' => $issued],
                $this->actor,
            );

            if ($paidOn !== null) {
                app(MarkInstallmentPaid::class)($invoice->installments()->sole(), SettledVia::Transfer, $paidOn, null, $this->actor);
            }
        } finally {
            Carbon::setTestNow();
        }

        return $invoice;
    }

    private function creditNote(Invoice $invoice, int $amount, string $on): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($on)->setTime(10, 0));

        try {
            app(IssueCreditNote::class)($invoice->fresh(), $amount, 'Nachlass', $this->actor, $on);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function project(string $name): ?ClientCase
    {
        return ClientCase::query()->where('name', 'like', "$name%")->first();
    }

    /** A date in the demo years, never after today. */
    private function day(int $year, int $month, int $day): string
    {
        return min(CarbonImmutable::create($year, $month, $day), CarbonImmutable::today())->toDateString();
    }
}
