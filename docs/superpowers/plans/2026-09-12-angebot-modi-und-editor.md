# Angebot: zwei Modi und ein neuer Editor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Angebot ist entweder klassisch oder ein Auswahlangebot, umschaltbar im Editor, und beide teilen Empfänger, Positionen, Zahlung, Vorschau und Versand.

**Architecture:** Eine Migration auf `quotes` trägt Modus, Empfänger, Zeitzeilen-Texte und den Versandweg. Der Controller bekommt einen Payload für beide Modi statt zwei. Der Editor wird als Kartenspalte neu gebaut (`QuoteEditor.vue` mit einer Karte je Abschnitt) und bekommt die Vorschau, die Verträge schon haben.

**Tech Stack:** Laravel 11 (Pest), Inertia + Vue 3 + Tailwind, shadcn-vue aus `resources/js/ui`, Editor.js nur für Dokumentvorlagen (hier nicht), Gotenberg für PDF.

**Spec:** `docs/superpowers/specs/2026-09-12-angebot-modi-und-editor-design.md`

## Global Constraints

- Tests laufen im Container, Admin-Oberfläche muss gesetzt sein: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=<Name>` — immer aus dem Repo-Root, nie aus `apps/studio`.
- Frontend-Tests: `npm --prefix apps/studio test` (Vitest, `--passWithNoTests`).
- Formulare benutzen die Komponenten aus `resources/js/ui` (`Input`, `Button`, `Label`, `ChoiceGroup`), keine rohen `<input>`.
- Deutsche UI-Kopie: kurz, Substantiv statt Satz, generisches Maskulinum, keine Emoji, keine Ausrufezeichen. Neue Schlüssel nach `resources/js/locales/de.json` unter `quotes`.
- Commit-Stil: Conventional Commits, Betreff ≤ 50 Zeichen.
- Domänenlayout: neuer Code liegt bei seinesgleichen unter `apps/studio/app/Quotes/{Actions,Data,Http,Models}`.
- `pricing_mode` kennt genau zwei Werte: `classic`, `choice`.
- `sent_channel` kennt genau zwei Werte: `mail`, `self`.

---

### Task 1: `pricing_mode` als Feld

**Files:**
- Create: `apps/studio/database/migrations/2026_09_12_000100_add_pricing_mode_to_quotes_table.php`
- Create: `apps/studio/app/Quotes/Enums/PricingMode.php`
- Modify: `apps/studio/app/Quotes/Models/Quote.php` (`$fillable`, `casts()`)
- Modify: `apps/studio/app/Quotes/Data/QuoteData.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php` (Validierung + `update()`)
- Test: `apps/studio/tests/Feature/Quotes/QuoteModeTest.php`

**Interfaces:**
- Produces: `App\Quotes\Enums\PricingMode` mit `Classic` (`classic`) und `Choice` (`choice`); `Quote->pricing_mode` als `PricingMode`; `QuoteData->pricingMode` als `string`.

- [ ] **Step 1: Test schreiben**

```php
<?php

use App\Quotes\Enums\PricingMode;
use App\Quotes\Models\Quote;

it('legt ein Angebot klassisch an', function () {
    $quote = Quote::factory()->create();

    expect($quote->pricing_mode)->toBe(PricingMode::Classic);
});

it('nimmt den Modus vom Formular entgegen', function () {
    [$user, $project] = $this->studioWithProject();

    $quote = Quote::factory()->for($project)->create();

    $this->actingAs($user)
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'choice',
            'hourly_rate' => 25000,
            'min_hours' => 3,
            'max_hours' => 12,
            'hours' => 5,
            'items' => [],
            'stages' => [],
        ])
        ->assertRedirect();

    expect($quote->fresh()->pricing_mode)->toBe(PricingMode::Choice);
});
```

`studioWithProject()` ist der Helfer, den `QuoteSliderTest.php` benutzt — dort nachsehen und genauso aufrufen.

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteModeTest`
Expected: FAIL, `Class "App\Quotes\Enums\PricingMode" not found`

- [ ] **Step 3: Enum anlegen**

```php
<?php

namespace App\Quotes\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PricingMode: string
{
    case Classic = 'classic';
    case Choice = 'choice';
}
```

- [ ] **Step 4: Migration schreiben**

```php
public function up(): void
{
    Schema::table('quotes', function (Blueprint $table) {
        $table->string('pricing_mode')->default('classic')->after('status');
    });

    DB::table('quotes')->whereNotNull('hourly_rate')->update(['pricing_mode' => 'choice']);
}

public function down(): void
{
    Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('pricing_mode'));
}
```

- [ ] **Step 5: Model und Data erweitern**

`Quote::$fillable` bekommt `'pricing_mode'`, `casts()` bekommt `'pricing_mode' => PricingMode::class`. In `QuoteData` ein `public string $pricingMode` neben `status`, gefüllt aus `$quote->pricing_mode->value`.

- [ ] **Step 6: Controller**

In `update()` die Regel `'pricing_mode' => ['required', Rule::enum(PricingMode::class)]` ergänzen und den Wert in den `$quote->update([...])`-Block aufnehmen.

- [ ] **Step 7: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteModeTest`
Expected: PASS

- [ ] **Step 8: Migration gegen die Entwicklungsdatenbank fahren**

Run: `docker compose exec api php artisan migrate`

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/database/migrations/2026_09_12_000100_add_pricing_mode_to_quotes_table.php apps/studio/tests/Feature/Quotes/QuoteModeTest.php
git commit -m "feat(quotes): make pricing mode an explicit field"
```

---

### Task 2: Nummer beim Anlegen

**Files:**
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php:30-90` (`store()`)
- Modify: `apps/studio/app/Quotes/Actions/SendQuote.php:45-60`
- Test: `apps/studio/tests/Feature/Quotes/QuoteNumberTest.php`

**Interfaces:**
- Consumes: `App\Numbering\Actions\AllocateDocumentNumber::__invoke(?string $tenantId = null, ?int $year = null, DocumentType $documentType = DocumentType::Quote): array` mit Schlüssel `number`.
- Produces: `Quote->number` ist ab dem Anlegen gesetzt; `SendQuote` vergibt keine Nummer mehr.

- [ ] **Step 1: Test schreiben**

```php
<?php

use App\Quotes\Models\Quote;

it('vergibt die Nummer beim Anlegen', function () {
    [$user, $project] = $this->studioWithProject();

    $this->actingAs($user)->post("/auftraege/{$project->public_id}/angebote", [])->assertRedirect();

    expect(Quote::query()->latest('id')->first()->number)->not->toBeNull();
});

it('behaelt die Nummer beim Versenden', function () {
    [$user, $project] = $this->studioWithProject();

    $this->actingAs($user)->post("/auftraege/{$project->public_id}/angebote", []);
    $quote = Quote::query()->latest('id')->first();
    $number = $quote->number;

    $this->actingAs($user)->post("/angebote/{$quote->public_id}/versenden")->assertRedirect();

    expect($quote->fresh()->number)->toBe($number);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteNumberTest`
Expected: FAIL, `number` ist null nach dem Anlegen

- [ ] **Step 3: `store()` vergibt die Nummer**

`AllocateDocumentNumber` in die Signatur von `store()` aufnehmen und den Aufruf in die Transaktion legen, in der das Angebot entsteht:

```php
$allocation = ($allocate)(documentType: DocumentType::Quote);
```

Das Ergebnis als `'number' => $allocation['number']` in die Erzeugung des Angebots geben.

- [ ] **Step 4: `SendQuote` räumt auf**

Den `AllocateDocumentNumber`-Aufruf samt Konstruktor-Abhängigkeit entfernen; `update()` setzt nur noch `status` und `sent_at`. Den Kommentar über der entfernten Zeile mit ersetzen, nicht stehen lassen.

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="QuoteNumberTest|QuoteTest|QuoteSliderTest|QuoteLetterAndAddendumTest"`
Expected: PASS. Schlägt ein bestehender Test fehl, weil er „Entwurf ohne Nummer" behauptet, ist die Erwartung im Test zu ändern — §4.5 der Spec.

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/tests/Feature/Quotes
git commit -m "feat(quotes): allocate the number at creation"
```

---

### Task 3: Empfänger am Angebot

**Files:**
- Create: `apps/studio/database/migrations/2026_09_12_000200_add_recipient_to_quotes_table.php`
- Modify: `apps/studio/app/Quotes/Models/Quote.php`
- Modify: `apps/studio/app/Quotes/Data/QuoteData.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Create: `apps/studio/app/Quotes/Actions/ResolveQuoteRecipient.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteRecipientTest.php`

**Interfaces:**
- Produces: `ResolveQuoteRecipient::__invoke(Project $project): ?Contact` — der Kontakt mit Rolle `payer`, sonst `primary`, sonst `null`.
- Produces: `Quote->recipient_contact_id`, `recipient_street`, `recipient_postcode`, `recipient_city`.

- [ ] **Step 1: Test schreiben**

```php
<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Quotes\Models\Quote;

it('belegt den Empfaenger mit dem Zahler vor', function () {
    [$user, $project] = $this->studioWithProject();

    $payer = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($payer, ProjectContactRole::Payer);

    $this->actingAs($user)->post("/auftraege/{$project->public_id}/angebote", [])->assertRedirect();

    $quote = Quote::query()->latest('id')->first();

    expect($quote->recipient_contact_id)->toBe($payer->id)
        ->and($quote->recipient_street)->toBe('Elbchaussee 88')
        ->and($quote->recipient_city)->toBe('Hamburg');
});

it('traegt die Anschrift in einen Kontakt ohne Anschrift nach', function () {
    [$user, $project] = $this->studioWithProject();

    $contact = Contact::factory()->create(['street' => null, 'postcode' => null, 'city' => null]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $quote = Quote::factory()->for($project)->create(['recipient_contact_id' => $contact->id]);

    $this->actingAs($user)->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'recipient_contact' => $contact->public_id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
        'items' => [],
        'stages' => [],
    ])->assertRedirect();

    expect($contact->fresh()->street)->toBe('Hafenstraße 12');
});

it('laesst eine abweichende Anschrift am Kontakt unberuehrt', function () {
    [$user, $project] = $this->studioWithProject();

    $contact = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $quote = Quote::factory()->for($project)->create(['recipient_contact_id' => $contact->id]);

    $this->actingAs($user)->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'recipient_contact' => $contact->public_id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
        'items' => [],
        'stages' => [],
    ])->assertRedirect();

    expect($contact->fresh()->street)->toBe('Elbchaussee 88')
        ->and($quote->fresh()->recipient_street)->toBe('Hafenstraße 12');
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteRecipientTest`
Expected: FAIL, Spalte `recipient_contact_id` fehlt

- [ ] **Step 3: Migration**

```php
Schema::table('quotes', function (Blueprint $table) {
    $table->foreignId('recipient_contact_id')->nullable()->after('project_id')->constrained('contacts')->nullOnDelete();
    $table->string('recipient_street')->nullable()->after('recipient_contact_id');
    $table->string('recipient_postcode', 32)->nullable()->after('recipient_street');
    $table->string('recipient_city')->nullable()->after('recipient_postcode');
});
```

- [ ] **Step 4: `ResolveQuoteRecipient`**

```php
<?php

namespace App\Quotes\Actions;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;

/** Wer zahlt, bekommt den Brief; sonst der Hauptkontakt. */
class ResolveQuoteRecipient
{
    public function __invoke(Project $project): ?Contact
    {
        $contacts = $project->contacts()->get();

        return $contacts->firstWhere('pivot.role', ProjectContactRole::Payer->value)
            ?? $contacts->firstWhere('pivot.role', ProjectContactRole::Primary->value)
            ?? $contacts->first();
    }
}
```

- [ ] **Step 5: `store()` belegt vor**

Den Kontakt über `ResolveQuoteRecipient` holen und `recipient_contact_id` samt der drei Adressfelder aus dem Kontakt in die Erzeugung geben.

- [ ] **Step 6: `update()` speichert und trägt nach**

Regeln ergänzen:

```php
'recipient_contact' => ['nullable', 'string'],
'recipient_street' => ['nullable', 'string', 'max:255'],
'recipient_postcode' => ['nullable', 'string', 'max:32'],
'recipient_city' => ['nullable', 'string', 'max:255'],
```

Den Kontakt über `public_id` innerhalb des Projekts auflösen (nie über die Roh-ID aus dem Formular), die drei Felder am Angebot speichern, und wenn der Kontakt keine Anschrift trägt, dieselben Werte dort setzen. Ein zweiter Endpunkt für „Beim Kontakt speichern" kommt in Task 10.

- [ ] **Step 7: `QuoteData` erweitert**

`recipientName`, `recipientStreet`, `recipientPostcode`, `recipientCity` und `recipientContact` (public_id) ergänzen.

- [ ] **Step 8: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteRecipientTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/database/migrations apps/studio/tests/Feature/Quotes
git commit -m "feat(quotes): carry the recipient on the quote"
```

---

### Task 4: Zeitzeile und Validierung

**Files:**
- Create: `apps/studio/database/migrations/2026_09_12_000300_add_time_line_to_quotes_table.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Modify: `apps/studio/app/Quotes/Models/Quote.php`
- Modify: `apps/studio/app/Quotes/Data/QuoteData.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteTimeLineTest.php`

**Interfaces:**
- Produces: `Quote->time_title`, `Quote->time_description`; Validierungsregeln, die im Modus `choice` greifen.

- [ ] **Step 1: Test schreiben**

```php
<?php

use App\Quotes\Models\Quote;

it('weist eine Vorauswahl ausserhalb der Spanne zurueck', function () {
    [$user, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create();

    $this->actingAs($user)
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'choice',
            'hourly_rate' => 25000,
            'min_hours' => 3,
            'max_hours' => 12,
            'hours' => 20,
            'items' => [],
            'stages' => [],
        ])
        ->assertSessionHasErrors('hours');
});

it('weist max_hours unter min_hours zurueck', function () {
    [$user, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create();

    $this->actingAs($user)
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'choice',
            'hourly_rate' => 25000,
            'min_hours' => 8,
            'max_hours' => 4,
            'items' => [],
            'stages' => [],
        ])
        ->assertSessionHasErrors('max_hours');
});

it('speichert Titel und Beschreibung der Zeitzeile', function () {
    [$user, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create();

    $this->actingAs($user)->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 5,
        'time_title' => 'Hochzeitsreportage',
        'time_description' => 'Trauung, Empfang, Paarshooting',
        'items' => [],
        'stages' => [],
    ])->assertRedirect();

    expect($quote->fresh()->time_title)->toBe('Hochzeitsreportage');
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteTimeLineTest`
Expected: FAIL, keine Fehlermeldung für `hours`

- [ ] **Step 3: Migration**

```php
Schema::table('quotes', function (Blueprint $table) {
    $table->string('time_title')->nullable()->after('hours');
    $table->string('time_description')->nullable()->after('time_title');
});
```

- [ ] **Step 4: Regeln schärfen**

Die bestehenden Regeln für den Reglerteil ersetzen:

```php
'hourly_rate' => ['nullable', 'integer', 'min:1', 'required_if:pricing_mode,choice'],
'min_hours' => ['nullable', 'integer', 'min:1', 'max:2000', 'required_if:pricing_mode,choice'],
'max_hours' => ['nullable', 'integer', 'min:1', 'max:2000', 'required_if:pricing_mode,choice', 'gte:min_hours'],
'images_per_hour' => ['nullable', 'integer', 'min:1', 'max:2000'],
'hours' => ['nullable', 'integer', 'min:1', 'max:2000', 'gte:min_hours', 'lte:max_hours'],
'time_title' => ['nullable', 'string', 'max:255'],
'time_description' => ['nullable', 'string', 'max:255'],
```

`time_title` und `time_description` in den `update([...])`-Block aufnehmen, `Quote::$fillable` ergänzen, `QuoteData` um `timeTitle` und `timeDescription` erweitern.

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="QuoteTimeLineTest|QuoteSliderTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/database/migrations apps/studio/tests/Feature/Quotes
git commit -m "feat(quotes): name the time line, validate its range"
```

---

### Task 5: Ein Payload für beide Modi

**Files:**
- Modify: `apps/studio/resources/js/admin/components/QuoteWorkspace.vue:121-196`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteModeTest.php` (erweitern)

**Interfaces:**
- Produces: ein `payload(draft)` in `QuoteWorkspace.vue`, das für beide Modi dieselben Schlüssel schickt: `pricing_mode`, `items[]` mit `quantity`, `unit_price`, `discount_type`, `discount_value`, `selectable`, `selected`, `outcome`, `stages[]`.

- [ ] **Step 1: Test ergänzen**

```php
it('speichert Menge und Rabatt auch im Auswahlmodus', function () {
    [$user, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create();

    $this->actingAs($user)->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 5,
        'items' => [[
            'title' => 'Fine-Art-Album',
            'quantity' => 2,
            'unit_price' => 69000,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'selectable' => true,
            'selected' => false,
        ]],
        'stages' => [
            ['percent' => 30, 'label' => 'Bei der Zusage', 'minimum' => 50000],
            ['percent' => 70, 'label' => 'Am Termin', 'minimum' => 0],
        ],
    ])->assertRedirect();

    $item = $quote->fresh()->items()->first();

    expect($item->quantity)->toBe(2)
        ->and($item->discount_value)->toBe(10)
        ->and($quote->fresh()->paymentStages()->count())->toBe(2);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteModeTest`
Expected: FAIL bei `quantity` oder `discount_value`

- [ ] **Step 3: Controller nimmt Stufen in beiden Modi entgegen**

Im `update()` den Zweig, der Stufen nur bei gesetztem `hourly_rate` schreibt, auf `pricing_mode`-unabhängiges Speichern umstellen: Stufen werden immer ersetzt, wenn der Schlüssel `stages` mitkommt.

- [ ] **Step 4: `QuoteWorkspace.vue` schickt einen Payload**

`save()` verliert die Weiche über `rate !== null`; `sliderPayload()` und der klassische Zweig werden zu einem `payload(draft)`, das `pricing_mode` mitschickt und die Zeilen aus einer Liste baut (Menge, Preis, Rabatt, `selectable`, `selected`, `outcome`, `id`).

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="QuoteModeTest|QuoteSliderTest|QuoteTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/resources/js/admin/components/QuoteWorkspace.vue apps/studio/tests/Feature/Quotes
git commit -m "refactor(quotes): one payload for both modes"
```

---

### Task 6: Versendet ohne Mail

**Files:**
- Create: `apps/studio/database/migrations/2026_09_12_000400_add_sent_channel_to_quotes_table.php`
- Create: `apps/studio/app/Quotes/Actions/MarkQuoteSent.php`
- Modify: `apps/studio/app/Quotes/Actions/SendQuote.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Modify: `apps/studio/routes/admin.php:554`
- Test: `apps/studio/tests/Feature/Quotes/QuoteMarkSentTest.php`

**Interfaces:**
- Produces: `MarkQuoteSent::__invoke(Quote $quote, string $channel, ?User $actor = null): Quote` — setzt `status`, `sent_at`, `sent_channel`, ruft `snapshotVat()`, schreibt ins Audit-Log, verschickt nichts.
- Consumes: `SendQuote` ruft `MarkQuoteSent` mit `channel: 'mail'` und hängt nur die Benachrichtigung an.

- [ ] **Step 1: Test schreiben**

```php
<?php

use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use Illuminate\Support\Facades\Notification;

it('setzt versendet ohne Mail', function () {
    Notification::fake();

    [$user, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create(['status' => QuoteStatus::Draft]);

    $this->actingAs($user)->post("/angebote/{$quote->public_id}/versendet")->assertRedirect();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Sent)
        ->and($quote->sent_channel)->toBe('self')
        ->and($quote->vat_mode)->not->toBeNull();

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteMarkSentTest`
Expected: FAIL, Route `/angebote/{quote}/versendet` fehlt

- [ ] **Step 3: Migration**

```php
Schema::table('quotes', fn (Blueprint $table) => $table->string('sent_channel', 16)->nullable()->after('sent_at'));
```

- [ ] **Step 4: `MarkQuoteSent` schreiben**

Den Rumpf aus `SendQuote::__invoke()` übernehmen: Wächter gegen einen Status ungleich `Draft`, Transaktion, `snapshotVat()`, `update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'sent_channel' => $channel])`, `AuditLogger::record(event: 'quote.sent', ...)` mit dem Weg im Kontext.

- [ ] **Step 5: `SendQuote` benutzt die Aktion**

`SendQuote` ruft `($this->mark)($quote, 'mail', $actor)` und danach die Benachrichtigung. Der Wächter und die Transaktion stehen nur noch in `MarkQuoteSent`.

- [ ] **Step 6: Route und Controller**

```php
Route::post('/angebote/{quote}/versendet', [QuoteController::class, 'markSent'])->name('quotes.mark-sent');
```

`markSent()` autorisiert wie `send()` über `manageQuotes` und ruft `MarkQuoteSent` mit `'self'`.

- [ ] **Step 7: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="QuoteMarkSentTest|QuoteTest"`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/routes/admin.php apps/studio/database/migrations apps/studio/tests/Feature/Quotes
git commit -m "feat(quotes): mark a quote sent without mail"
```

---

### Task 7: PDF und Portal sprechen die Zeitzeile aus

**Files:**
- Modify: `apps/studio/app/Quotes/Actions/RenderQuotePdf.php:95-105`
- Modify: `apps/studio/resources/js/portal/components/QuoteSlider.vue:60-70`
- Modify: `apps/studio/resources/js/locales/de.json` (`quotes`)
- Test: `apps/studio/tests/Feature/Quotes/QuoteTimeLineTest.php` (erweitern)

**Interfaces:**
- Consumes: `Quote->time_title`, `Quote->time_description`, `Quote->images_per_hour`, `Quote->hours`.

- [ ] **Step 1: Test ergänzen**

```php
it('schreibt Titel und Stunden ins PDF', function () {
    [$user, $project] = $this->studioWithProject();

    $quote = Quote::factory()->for($project)->create([
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 8,
        'images_per_hour' => 40,
        'time_title' => 'Hochzeitsreportage',
        'time_description' => 'Trauung, Empfang, Paarshooting',
    ]);

    $html = app(App\Quotes\Actions\RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('Hochzeitsreportage')
        ->and($html)->toContain('8 Stunden')
        ->and($html)->toContain('ca. 320');
});
```

Trägt `RenderQuotePdf` noch keine Methode, die das HTML ohne Gotenberg zurückgibt, wird sie in diesem Schritt herausgezogen: `html(Quote $quote): string` mit dem, was heute vor dem Gotenberg-Aufruf steht. `__invoke()` ruft sie.

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteTimeLineTest`
Expected: FAIL, „Hochzeitsreportage" fehlt

- [ ] **Step 3: Zeile im PDF**

Die Zeile, die heute `$hours.' Stunden'` schreibt, baut den Titel als Namen und die Zusatzzeile aus Beschreibung, Stunden und — nur wenn `images_per_hour` gesetzt ist — `'ca. '.($hours * $quote->images_per_hour).' bearbeitete Bilder'`.

- [ ] **Step 4: Portal**

In `QuoteSlider.vue` das Label der führenden Zeile auf `quote.timeTitle ?? t('quotes.hoursCount', hours)` setzen und darunter dieselbe Zusatzzeile zeigen. Neue Schlüssel in `de.json`:

```json
"timeTitle": "Titel",
"timeDescription": "Beschreibung",
"approxImages": "ca. {count} bearbeitete Bilder"
```

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="QuoteTimeLineTest|QuoteSliderTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/resources/js apps/studio/tests/Feature/Quotes
git commit -m "feat(quotes): print the time line by name"
```

---

### Task 8: Der Editor als Kartenspalte

**Files:**
- Create: `apps/studio/resources/js/admin/components/quote/QuoteEditor.vue`
- Create: `apps/studio/resources/js/admin/components/quote/QuoteCard.vue`
- Create: `apps/studio/resources/js/admin/components/quote/QuoteRecipientCard.vue`
- Create: `apps/studio/resources/js/admin/components/quote/QuoteTimeCard.vue`
- Create: `apps/studio/resources/js/admin/components/quote/QuotePositionsCard.vue`
- Create: `apps/studio/resources/js/admin/components/quote/QuotePositionRow.vue`
- Create: `apps/studio/resources/js/admin/components/quote/QuoteTermsCard.vue`
- Modify: `apps/studio/resources/js/admin/components/QuoteWorkspace.vue`
- Delete: `apps/studio/resources/js/admin/components/QuoteSliderEditor.vue`, `apps/studio/resources/js/admin/components/QuoteTable.vue`
- Modify: `apps/studio/resources/js/locales/de.json`
- Test: `apps/studio/resources/js/admin/components/quote/quoteTotals.spec.ts`
- Create: `apps/studio/resources/js/admin/components/quote/quoteTotals.ts`

**Interfaces:**
- Produces: `quoteTotals.ts` mit `lineAmount(row): number`, `subtotal(rows, timeTotal): number`, `applyDiscount(amount, discount): number`, `span(rows, hours, rate, withOptional): number` — alle in Cent.
- Consumes: `QuoteEditor.vue` bekommt `v-model` auf den Entwurf aus `QuoteWorkspace.vue` und stößt `change` an, das `save()` debounced auslöst.

Der Canvas ist die Vorlage: Karten in einer Spalte von 720 px, geöffnet starten Empfänger und Positionen, dazu Zeit im Modus Auswahl; geschlossene Karten tragen eine Zusammenfassung.

- [ ] **Step 1: Rechentest schreiben**

```ts
import { describe, expect, it } from 'vitest'
import { applyDiscount, lineAmount, span } from './quoteTotals'

describe('quoteTotals', () => {
  it('rechnet Menge mal Preis', () => {
    expect(lineAmount({ quantity: 2, unitPrice: 69000, discountType: null, discountValue: null })).toBe(138000)
  })

  it('zieht einen Prozentrabatt ab', () => {
    expect(lineAmount({ quantity: 1, unitPrice: 100000, discountType: 'percentage', discountValue: 10 })).toBe(90000)
  })

  it('faellt nie unter null', () => {
    expect(applyDiscount(1000, { type: 'amount', value: 5000 })).toBe(0)
  })

  it('rechnet die Spanne aus Stunden und Zeilen', () => {
    const rows = [
      { quantity: 1, unitPrice: 40000, discountType: null, discountValue: null, selectable: false },
      { quantity: 1, unitPrice: 69000, discountType: null, discountValue: null, selectable: true },
    ]

    expect(span(rows, 3, 25000, false)).toBe(115000)
    expect(span(rows, 12, 25000, true)).toBe(409000)
  })
})
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `npm --prefix apps/studio test`
Expected: FAIL, `quoteTotals.ts` fehlt

- [ ] **Step 3: `quoteTotals.ts` schreiben**

Reine Funktionen, keine Vue-Abhängigkeit, alles in Cent. `span(rows, hours, rate, withOptional)` = `hours * rate` plus die Zeilen, im Fall `withOptional = false` ohne die wählbaren.

- [ ] **Step 4: Test laufen lassen**

Run: `npm --prefix apps/studio test`
Expected: PASS

- [ ] **Step 5: Karten bauen**

`QuoteCard.vue` ist die Hülle: Titel, Zusammenfassung, Pfeil, `open`-Zustand über `v-model`. Die vier Karten füllen sie mit den Feldern aus dem Canvas. `QuotePositionRow.vue` ist eine Position als Karte, offen ist höchstens eine (der Index liegt in `QuotePositionsCard.vue`).

- [ ] **Step 6: `QuoteWorkspace.vue` umstellen**

Statt `QuoteSliderEditor` und `QuoteTable` rendert der Workspace `QuoteEditor`. Der Umschalter `Klassisch | Auswahl` sitzt über den Karten und setzt `draft.pricingMode`.

- [ ] **Step 7: Alte Komponenten löschen**

`QuoteSliderEditor.vue` und `QuoteTable.vue` entfernen, danach `rg "QuoteSliderEditor|QuoteTable" apps/studio/resources` — es darf kein Treffer bleiben.

- [ ] **Step 8: Typen und Tests**

Run: `npm --prefix apps/studio run lint && npm --prefix apps/studio test`
Expected: beides grün

- [ ] **Step 9: Commit**

```bash
git add apps/studio/resources/js apps/studio/resources/js/locales/de.json
git commit -m "feat(quotes): rebuild the editor as cards"
```

---

### Task 9: Vorschau neben dem Editor

**Files:**
- Modify: `apps/studio/routes/admin.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Create: `apps/studio/resources/js/admin/components/quote/QuotePreviewPane.vue`
- Modify: `apps/studio/resources/js/admin/components/quote/QuoteEditor.vue`
- Test: `apps/studio/tests/Feature/Quotes/QuotePreviewTest.php`

**Interfaces:**
- Produces: `POST /angebote/{quote}/vorschau` gibt `text/html` zurück — dasselbe Dokument wie `RenderQuotePdf::html()`, gerechnet auf den Feldern aus dem Request statt auf dem gespeicherten Stand.

- [ ] **Step 1: Test schreiben**

```php
<?php

use App\Quotes\Models\Quote;

it('rendert die Vorschau als HTML', function () {
    [$user, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create(['time_title' => 'Hochzeitsreportage']);

    $this->actingAs($user)
        ->post("/angebote/{$quote->public_id}/vorschau", ['intro' => 'Liebe Lena,'])
        ->assertOk()
        ->assertSee('Liebe Lena,', escape: false);
});

it('haelt Fremde von der Vorschau fern', function () {
    [, $project] = $this->studioWithProject();
    $quote = Quote::factory()->for($project)->create();

    $this->post("/angebote/{$quote->public_id}/vorschau")->assertRedirect('/login');
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuotePreviewTest`
Expected: FAIL, Route fehlt

- [ ] **Step 3: Route und Methode**

`preview()` autorisiert über `manageQuotes`, validiert dieselben Felder wie `update()`, füllt ein nicht gespeichertes `Quote`-Exemplar damit und gibt `RenderQuotePdf::html()` als `response($html, 200, ['Content-Type' => 'text/html'])` zurück.

- [ ] **Step 4: Pane bauen**

`QuotePreviewPane.vue` folgt `DocumentPreviewPane.vue`: Blatt 794×1123 px, 38 px Rand, `iframe` mit `sandbox=""` und `srcdoc`, Antwort über `useDebounceFn` mit 400 ms und dem monoton steigenden Token gegen überholende Antworten. Nicht kopieren, was nicht gebraucht wird — der lokale Sofort-Paint der Vorlagen hängt an Editor.js und entfällt hier.

- [ ] **Step 5: Spalte einhängen**

In `QuoteEditor.vue` das Raster `minmax(0, 1fr) 420px` ab 1180 px, darunter eine Spalte und die Vorschau hinter einem Knopf in der Kopfzeile.

- [ ] **Step 6: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuotePreviewTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Quotes apps/studio/routes/admin.php apps/studio/resources/js apps/studio/tests/Feature/Quotes
git commit -m "feat(quotes): show the sheet beside the editor"
```

---

### Task 10: Kopfzeile, Versandmenü, Adressübernahme

**Files:**
- Modify: `apps/studio/resources/js/admin/components/quote/QuoteEditor.vue`
- Modify: `apps/studio/resources/js/admin/components/QuoteWorkspace.vue`
- Modify: `apps/studio/routes/admin.php`
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Modify: `apps/studio/resources/js/locales/de.json`
- Test: `apps/studio/tests/Feature/Quotes/QuoteRecipientTest.php` (erweitern)

**Interfaces:**
- Produces: `POST /angebote/{quote}/empfaenger-uebernehmen` schreibt die Anschrift des Angebots in den Kontakt.

- [ ] **Step 1: Test ergänzen**

```php
it('uebernimmt die Anschrift auf Knopfdruck in den Kontakt', function () {
    [$user, $project] = $this->studioWithProject();

    $contact = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $quote = Quote::factory()->for($project)->create([
        'recipient_contact_id' => $contact->id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
    ]);

    $this->actingAs($user)->post("/angebote/{$quote->public_id}/empfaenger-uebernehmen")->assertRedirect();

    expect($contact->fresh()->street)->toBe('Hafenstraße 12');
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=QuoteRecipientTest`
Expected: FAIL, Route fehlt

- [ ] **Step 3: Route und Methode**

- [ ] **Step 4: Kopfzeile bauen**

Reihenfolge aus dem Canvas: Nummer und Status links, rechts `Download`, `Als Paket sichern`, dann der geteilte Sendeknopf. Die Fläche ruft `/angebote/{id}/versenden`, das Menü dahinter bietet `Link kopieren` (Portal-Adresse in die Zwischenablage) und `Versendet` (`/angebote/{id}/versendet`). Menü über `DropdownMenu` aus `resources/js/ui`, nicht selbst gebaut.

- [ ] **Step 5: Kopie**

Neue Schlüssel unter `quotes`: `download`, `sendByMail`, `copyLink`, `markSent`, `recipient`, `saveToContact`, `differsFromContact`, `mode`, `modeClassic`, `modeChoice`, `preselection`. Jeder Wert ist ein Substantiv oder eine knappe Handlung, keine Erklärung.

- [ ] **Step 6: Tests laufen lassen**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter=Quote && npm --prefix apps/studio run lint`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add apps/studio
git commit -m "feat(quotes): header actions and send menu"
```

---

### Task 11: Screenshots und Abschluss

**Files:**
- Modify: `docs/superpowers/specs/2026-09-12-angebot-modi-und-editor-design.md` (Status auf `umgesetzt`)
- Create: `docs/screenshots/2026-09-12-angebot-klassisch.png`, `docs/screenshots/2026-09-12-angebot-auswahl.png`

- [ ] **Step 1: Volle Suite fahren**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test`
Expected: PASS

- [ ] **Step 2: Screenshots ziehen**

Beide Modi im laufenden Studio aufnehmen und unter den Namen oben ablegen.

- [ ] **Step 3: Commit**

```bash
git add docs
git commit -m "docs(quotes): record the new editor"
```

---

### Task 12: Die Anschrift steht auf dem Dokument

Nachgetragen während der Umsetzung: Task 3 legt `recipient_street`, `recipient_postcode` und `recipient_city` am Angebot an, aber `RenderQuotePdf` druckt sie nirgends — die Spec verlangt in §4.4 eine Anschrift, die der Brief trägt.

**Files:**
- Modify: `apps/studio/app/Quotes/Actions/RenderQuotePdf.php`
- Modify: `apps/studio/app/Documents/Support/DocumentMergeDataBuilder.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteRecipientTest.php` (erweitern)

**Interfaces:**
- Consumes: `Quote->recipient_contact_id`, `recipient_street`, `recipient_postcode`, `recipient_city`; `RenderQuotePdf::html(Quote): string`.

- [ ] **Step 1: Test ergänzen**

```php
it('druckt die Anschrift des Angebots, nicht die des Kontakts', function () {
    [$user, $project] = $this->studioWithProjectForRecipient();

    $contact = Contact::factory()->create(['name' => 'Ursula Meier', 'street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Payer);

    $quote = Quote::factory()->for($project)->create([
        'recipient_contact_id' => $contact->id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
    ]);

    $html = app(App\Quotes\Actions\RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('Ursula Meier')
        ->and($html)->toContain('Hafenstraße 12')
        ->and($html)->toContain('20459 Hamburg')
        ->and($html)->not->toContain('Elbchaussee 88');
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag bestätigen**

Run: `docker compose exec -e APP_KEY=base64:$(openssl rand -base64 32) -e REDIS_CLIENT=predis -e APP_SURFACE=admin api php artisan test --filter=QuoteRecipientTest`
Expected: FAIL, die Anschrift fehlt im Dokument

- [ ] **Step 3: Anschriftenfeld ins Dokument**

Der Empfängerblock steht oben links, über dem Datum: Name, Straße, dann PLZ und Ort in einer Zeile. Die Werte kommen vom Angebot, nicht vom Kontakt — ein Umzug nach dem Versenden ändert das verschickte Dokument nicht. Fehlt die Anschrift am Angebot, bleibt der Block beim Namen.

- [ ] **Step 4: Tests laufen lassen**

Run: `docker compose exec -e APP_KEY=base64:$(openssl rand -base64 32) -e REDIS_CLIENT=predis -e APP_SURFACE=admin api php artisan test --filter="QuoteRecipientTest|QuotePreviewTest|QuoteTimeLineTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Quotes/Actions/RenderQuotePdf.php apps/studio/app/Documents/Support/DocumentMergeDataBuilder.php apps/studio/tests/Feature/Quotes/QuoteRecipientTest.php
git commit -m "feat(quotes): print the recipient address"
```
