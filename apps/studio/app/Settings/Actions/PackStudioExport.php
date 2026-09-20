<?php

namespace App\Settings\Actions;

use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Gallery\Models\Gallery;
use App\Media\Actions\BuildGalleryZip;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\Asset;
use App\Media\Support\ZipBuildMember;
use App\Notifications\NotificationService;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Support\InvoiceFigures;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Enums\StudioExportKind;
use App\Settings\Models\StudioExport;
use App\Settings\Notifications\StudioExportReady;
use App\Settings\Support\StudioRecords;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\LiveUpdate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipStream\ZipStream;

/**
 * #489: baut das Archiv eines StudioExport. Der Weg von BuildGalleryZip
 * (S3 zu S3, nie auf die lokale Platte) trägt auch dieses.
 *
 * ponytail: ein Archiv, eine Datei. Die Volumen-Teilung bei 3,9 GiB gilt der
 * Auslieferung an Kunden; hier ist ZIP64 an. Teilen, sobald ein
 * Betriebssystem der Kunden das Archiv nicht öffnet.
 */
class PackStudioExport
{
    private const DOCUMENT_COLUMNS = ['Nummer', 'Datum', 'Betrag', 'Kontakt'];

    public function __construct(
        private readonly BuildGalleryZip $zips,
        private readonly StudioRecords $records,
        private readonly StudioSettingsResolver $settings,
        private readonly RenderQuotePdf $quotePdf,
        private readonly NotificationService $notifications,
    ) {}

    public function __invoke(StudioExport $export): void
    {
        if ($export->status !== ZipBuildStatus::Building) {
            return;
        }

        $path = "exports/{$export->tenant_id}/{$export->public_id}.zip";

        try {
            $size = $this->zips->stream($path, fn (ZipStream $zip) => match ($export->kind) {
                StudioExportKind::Daten => $this->daten($zip),
                StudioExportKind::Galerie => $this->galerie($zip, $export->gallery),
            }, zip64: true);
        } catch (Throwable $e) {
            $export->update(['status' => ZipBuildStatus::Failed, 'error' => Str::limit($e->getMessage(), 2000, '')]);
            LiveUpdate::publish();

            throw $e;
        }

        $export->update([
            'status' => ZipBuildStatus::Ready,
            'error' => null,
            'path' => $path,
            'size' => $size,
            'expires_at' => now()->addDays(StudioExport::VALID_DAYS),
        ]);

        LiveUpdate::publish();

        if ($export->requestedBy !== null) {
            $this->notifications->send(
                $export->requestedBy,
                new StudioExportReady($export->gallery?->title ?? 'Daten'),
            );
        }
    }

    private function daten(ZipStream $zip): void
    {
        $zip->addFile('kontakte.csv', StudioRecords::csv(StudioRecords::CONTACT_COLUMNS, $this->records->contactRows()));
        $zip->addFile('projekte.csv', StudioRecords::csv(StudioRecords::PROJECT_COLUMNS, $this->records->projectRows()));
        $zip->addFile('nachrichten.json', $this->messages());

        $rows = [];
        foreach (Invoice::query()->with(['installments', 'items', 'contact'])->orderBy('number')->get() as $invoice) {
            $rows[] = $this->row($invoice->number, $invoice->issued_at, InvoiceFigures::forInvoice($invoice, $this->settings)->gross, $invoice->contact?->name);
            $this->pdf($zip, 'rechnungen', 'rechnung', $invoice->number, $invoice->issued_at, $this->stored($invoice->pdf_path));
        }
        $zip->addFile('rechnungen/rechnungen.csv', StudioRecords::csv(self::DOCUMENT_COLUMNS, $rows));

        $rows = [];
        foreach (CreditNote::query()->with('invoice.contact')->orderBy('number')->get() as $note) {
            $rows[] = $this->row($note->number, $note->issued_at, $note->amount, $note->invoice->contact?->name);
            $this->pdf($zip, 'gutschriften', 'gutschrift', $note->number, $note->issued_at, $this->stored($note->pdf_path));
        }
        $zip->addFile('gutschriften/gutschriften.csv', StudioRecords::csv(self::DOCUMENT_COLUMNS, $rows));

        // Entwürfe haben keine Nummer und keinen Empfänger, den ein Steuerprüfer suchen würde.
        $rows = [];
        $quotes = Quote::query()->where('status', '!=', QuoteStatus::Draft)->whereNotNull('number')->with('recipientContact')->orderBy('number')->get();
        foreach ($quotes as $quote) {
            $date = $quote->sent_at ?? $quote->created_at;
            $rows[] = $this->row($quote->number, $date, (int) $quote->net + (int) $quote->vat_amount, $quote->recipientContact?->name);
            $this->pdf($zip, 'angebote', 'angebot', $quote->number, $date, ($this->quotePdf)($quote));
        }
        $zip->addFile('angebote/angebote.csv', StudioRecords::csv(self::DOCUMENT_COLUMNS, $rows));
    }

    /** @return list<string|null> */
    private function row(string $number, CarbonInterface $date, int $minorUnits, ?string $contact): array
    {
        return [$number, $date->format('d.m.Y'), StudioRecords::euro($minorUnits), $contact];
    }

    private function pdf(ZipStream $zip, string $folder, string $prefix, string $number, CarbonInterface $date, ?string $bytes): void
    {
        if ($bytes !== null) {
            $zip->addFile("{$folder}/{$date->year}/{$prefix}-".preg_replace('/[^\w.-]+/u', '-', $number).'.pdf', $bytes);
        }
    }

    private function stored(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('local')->get($path);
    }

    private function messages(): string
    {
        $threads = Thread::query()->with(['project', 'messages.contact', 'messages.author'])->orderBy('id')->get()
            ->map(fn (Thread $thread) => [
                'id' => $thread->public_id,
                'subject' => $thread->subject,
                'project' => $thread->project?->public_id,
                'messages' => $thread->messages->map(fn (Message $message) => [
                    'id' => $message->public_id,
                    'from' => $message->contact_id !== null ? 'contact' : 'staff',
                    'author' => ($message->contact ?? $message->author)->public_id,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->all(),
            ])->all();

        return json_encode($threads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function galerie(ZipStream $zip, Gallery $gallery): void
    {
        $assets = Asset::query()->where('gallery_id', $gallery->id)->get()->keyBy('key');

        $members = $gallery->draftAssets()
            ->map(fn (array $row) => $assets[$row['key']])
            ->map(fn (Asset $asset) => new ZipBuildMember($asset->key, $asset->filename, $asset->byte_size))
            ->values();

        $this->zips->addMembers($zip, Storage::disk('s3'), $members->all());
        $zip->addFile('bilder.csv', StudioRecords::csv(
            ['Bildnummer', 'Dateiname'],
            $members->map(fn (ZipBuildMember $member, int $index) => [$index + 1, $member->name]),
        ));
    }
}
