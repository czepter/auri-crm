# Angebot als Regler — design

**Date:** 2026-09-06
**Status:** design, keine Issues gefiled — das hier ist die Begründung; die Issues tragen den Scope, sobald sie stehen.

---

## 1. Warum das existiert

Das Portal zeigt ein Angebot heute als Positionsliste mit Summe darunter
(`apps/studio/resources/js/portal/pages/Quotes/Show.vue`), gespeist aus
`QuoteItem`-Zeilen mit `title`, `quantity`, `unit_price`. Der Admin-Screen
darüber (`apps/studio/resources/js/admin/pages/Projects/Quotes.vue` mit
`QuoteTable.vue`) ist dieselbe Tabelle mit Eingabefeldern. Das Portal spiegelt
den Admin, und beide sind eine Rechnung, die noch nicht fällig ist.

Für die Zielgruppe, um die es hier geht, ist das der Buchungskiller: ein
Pauschalpaket, das man annimmt oder nicht, ohne zu sehen, woraus der Preis
besteht. Was stattdessen gebraucht wird — Stundensatz statt Paket, Bausteine
statt Positionen, Zahlung in Stufen, alles auf einem Screen sichtbar —
verlangt Felder, die es im Datenmodell nicht gibt. Diese Spec beschreibt
diese Felder und die zwei Screens, die auf ihnen stehen.

Die Entwürfe liegen im Design-Canvas dieser Session, Seite „Regler +
Bausteine" und Seite „Studio".

## 2. Scope

**Drin:** ein Stundensatz mit Mindest- und Höchststunden an der Quelle des
Angebots; Bausteine als eigene, vom Kunden schaltbare Zeilen; eine
Zahlungsvorlage aus Anteilen mit eigenem Text und optionalem Mindestbetrag;
`AcceptQuote`, das die Auswahl des Kunden entgegennimmt statt nur eine Zusage;
die zwei Portal-Screens (Handy, Desktop) und der Studio-Screen; die Spanne, die
im Board und im PDF an die Stelle der einen Summe tritt.

**Draußen, benannt damit die Auslassungen Entscheidungen sind:** ein Regler
über etwas anderes als Stunden (Tage, Locations, Personen — nichts davon ist
angefragt, und jede zusätzliche Achse verdoppelt die Spanne); Preisstaffeln
nach Wochentag oder Saison (`Package` kennt heute keine Bedingungen, und ein
Angebot mit Wenn-Dann-Preis ist keine Transparenz mehr); ein Bild-Upload im Angebot selbst
(Bilder gehören an den Katalog und an die Studio-Einstellungen, §5.3, und ein
Angebot, das eigene Bilder hält, ist ein Medienordner mit Preisen); ein Kunde,
der Stunden nach der Zusage nachträglich verschiebt (das ist eine Vertragsänderung, und
`ReviseQuote` existiert dafür); ein automatisch generierter Ablaufplan aus den
Stunden (zum Angebotszeitpunkt steht der Tagesablauf nicht fest — genau deshalb
zeigt der Regler ein Bildraster und keine Zeitachse); Ratenzahlung über einen
externen Anbieter (Klarna und dergleichen — die Stufen hier sind Fälligkeiten
gegenüber dem Studio, keine Finanzierung durch einen Dritten); eine zweite
Darstellung als Wahlmöglichkeit im Studio (die vier verworfenen Entwürfe stehen
im Canvas, sie sind Referenz und kein Feature).

## 3. Die drei Screens

Die Bilder sind aus den Artboard-Quellen gerendert und liegen unter
`docs/screenshots/`.

| Bild | Artboard | Was es zeigt |
|---|---|---|
| `docs/screenshots/2026-09-06-angebot-handy.png` | Regler + Bausteine · Handy | Regler, Bildraster, Bausteine, klebende Zahlleiste am Fuß |
| `docs/screenshots/2026-09-06-angebot-desktop.png` | Regler + Bausteine · Desktop | dieselbe linke Spalte, Rail rechts mit Aufstellung und Stufen |
| `docs/screenshots/2026-09-06-angebot-studio.png` | Studio · Angebot bearbeiten | Stundensatz, Bausteine, Zahlstufen, Spanne |

### 3.1 Portal, Handy

Kopf: Stundenzahl groß, Bilderzahl rechts. Darunter der Regler über
`minHours`–`maxHours`. Über dem Regler ein Raster aus 24 Kacheln, eine je
zwanzig Bilder; alle 24 stehen immer im DOM und blenden auf `opacity:.1`
zurück, statt entfernt zu werden — das Wachsen ist eine Blende, kein Umbau.
Unter dem Regler eine Zeile, die den Umfang benennt, ohne Uhrzeiten zu
behaupten („Trauung, Empfang, Paarshooting · 250 € je Stunde").

Dann die Inklusiv-Chips, dann die Bausteine als Foto-Kacheln: eingeschaltet
volle Farbe mit Ring in `--accent`, ausgeschaltet `grayscale(1)`. Der Zustand
steckt im Bild.

Am Fuß eine klebende Leiste: Summe, darunter die Zahlstufen als Dreier-Raster
(Label in 10 px Versalien, Betrag darunter, erste Stufe in `--accent`), darunter
der Button über die volle Breite.

### 3.2 Portal, Desktop

Linke Spalte identisch zum Handy. Rechts ein klebendes Rail mit: Summe,
Aufstellung, Zahlstufen, Button.

Die Aufstellung ist der Grund für das Rail. Sie sagt, was der Betrag deckt, in
der Reihenfolge, in der er entsteht:

```
6 Stunden Reportage        1.500 €     ← bezahlt, Ink, fett
240 bearbeitete Bilder     enthalten   ← Folge, grau
Fünf davon in 48 Stunden   enthalten
Galerie mit Download       enthalten
Fahrt in Norddeutschland   enthalten
Content Creator              400 €     ← gewählter Baustein
Analog, 35 mm                250 €
```

Die Inklusiv-Chips der linken Spalte entfallen auf dem Desktop dafür — sonst
stehen dieselben vier Aussagen zweimal auf einem Screen.

### 3.3 Studio

Fünf Abschnitte im `ui/page/Section.vue`-Raster (Titel in der 112-px-Rinne):

- **Zeit** — Stundensatz, Ab, Bis, Bilder je Stunde. Eine Zeile daneben nennt
  sofort, was die Enden kosten.
- **Ohne Aufpreis** — die Zeilen, die im Portal als „enthalten" erscheinen. Die
  erste rechnet mit dem Regler mit und trägt „automatisch"; die übrigen schreibt
  der Fotograf.
- **Bausteine** — Bild, Name, die Zeile die der Kunde liest, Preis, und ein
  Schalter „vorausgewählt / wählbar". Das Bild ist hier eine Anzeige, kein
  Uploadfeld: gepflegt wird es am Katalogeintrag (§5.3), und wer es hier
  anklickt, landet dort.
- **Zahlung** — je Stufe Anteil, Freitext, Mindestbetrag; Zeilen kommen per Plus
  dazu und gehen per × weg. Die Summenprüfung wird rot, solange die Anteile
  nicht 100 ergeben oder die Mindestbeträge über dem kleinsten Angebot liegen.
- **Spanne** — kleinstes Angebot, Vorauswahl, größtes Angebot.

## 4. Warum eine Spanne und keine Summe

Ein Angebot mit Regler hat keinen Preis mehr, es hat einen Bereich. `Quote`
trägt heute genau ein `total`, und alles hängt daran: der Wert auf dem Board
(`ReadInquiryColumn`), das PDF (`RenderQuotePdf`), der Zahlungsplan.

Die Entscheidung ist, `total` **nicht** zu ersetzen, sondern es erst beim
Annehmen entstehen zu lassen. Vor der Zusage hat die Quote keinen Betrag,
sondern eine Konfiguration; die Spanne wird aus ihr berechnet, nirgends
gespeichert. `AcceptQuote` schreibt `total` — dann gilt für jeden nachgelagerten
Konsumenten wieder genau das, was heute gilt.

Für die Zeit davor brauchen Board und Liste eine Anzeige. Nicht ein zweites Feld
`total_max`, sondern ein berechnetes Paar aus derselben Konfiguration — sonst
gibt es zwei Wahrheiten, die auseinanderlaufen, sobald jemand einen Baustein
ändert.

## 5. Datenmodell

### 5.1 Quote und Package

Neu, auf beiden (`Package` als Vorlage, `Quote` als Kopie — die bestehende
`copyItems()`-Trennung bleibt, ein Paket ist nie eine lebende Referenz):

| Feld | Typ | Wofür |
|---|---|---|
| `hourly_rate` | int, minor units, nullable | Stundensatz. `null` heißt: klassisches Angebot ohne Regler, altes Verhalten. |
| `min_hours` | int, nullable | Reglerbeginn, Mindestbuchung. |
| `max_hours` | int, nullable | Reglerende. |
| `images_per_hour` | int, nullable | Nur Anzeige — die abgeleitete Bilderzahl im Portal. |
| `hours` | int, nullable | Was der Kunde gewählt hat. Vor der Zusage die Vorauswahl, danach die Zusage. |

`hourly_rate === null` ist die Weiche, an der beide Welten koexistieren: alle
bestehenden Angebote bleiben Positionslisten, und die neue Darstellung greift
nur, wo ein Satz gepflegt ist. Kein Feature-Flag, kein zweiter Screen — ein
Feld, das entweder gefüllt ist oder nicht.

### 5.2 QuoteItem und PackageItem

`QuoteItem` unterscheidet heute zwei Arten von Zeile über `unit_price === null`
(„Enthaltene Leistung"). Dazu kommt eine dritte:

| Feld | Typ | Wofür |
|---|---|---|
| `selectable` | bool, default false | Der Kunde kann diese Zeile im Portal an- und abschalten. |
| `selected` | bool, default false | Vorausgewählt beim Öffnen; nach der Zusage: gewählt. |
| `outcome` | string, nullable | Die Zeile, die der Kunde unter dem Namen liest („Vierzig Seiten, 30 × 30"). |
| `image_path` | string, nullable | Das Foto der Kachel, aus dem Katalog kopiert — §5.3. |

`selectable = false` ist der heutige Zustand, also ändert sich für bestehende
Zeilen nichts. Der Guard in `QuoteItem::booted()` bleibt, wie er ist — mit einer
Ausnahme in §6.

### 5.3 Woher die Bilder kommen

Zwei Stellen zeigen Fotos, und beide brauchen eine Quelle. Keine davon lädt ein
Bild im Angebot selbst hoch — ein Angebot ist ein Dokument, kein Medienordner.

**Die Baustein-Kachel** gehört zum Katalog, nicht zum Angebot. Ein Baustein wie
das Album ist über Dutzende Angebote dasselbe, also liegt sein Bild an
`PackageItem.image_path` und wird einmal gepflegt: in `/pakete/{package}`, wo
die Zeile ohnehin bearbeitet wird. `QuoteItem.image_path` ist die Kopie, die
`Package::copyItems()` mitnimmt — dieselbe Trennung, die der Docblock dort
schon begründet: tauscht der Fotograf später das Katalogbild, behalten
verschickte Angebote ihres.

**Das Raster über dem Regler** hat keinen Bezug zum einzelnen Angebot — es
zeigt Menge, nicht Inhalt. Es kommt aus einem Satz Schaufensterbilder auf
Studio-Ebene, gepflegt in den Studio-Einstellungen neben dem Logo, und wird
zyklisch über die 24 Kacheln gelegt. Unter sechs Bildern wiederholt sich das
sichtbar; sind gar keine gepflegt, entfällt das Raster und der Regler steht mit
seinen zwei Zahlen allein — die Bilderzahl trägt die Aussage dann selbst.

**Beides läuft über den Weg, den das Logo schon geht.** `StudioSettingsResolver::setLogo()`
nimmt eine `UploadedFile`, legt sie mit `storeAs("branding/{$tenant}", …)` auf
`Storage::disk('local')` und `BrandingController` liefert sie mit
`Storage::disk('local')->response()` aus. Dasselbe unter `catalog/{$tenant}/`
und `showcase/{$tenant}/`, mit einem Controller derselben Form. Keine
Media-Library, kein zweiter Disk, kein Bild-Modell.

**Nicht aus den Galerien.** Naheliegend, weil dort die Fotos liegen und
`GalleryVersion.cover_key` das Muster „ein Bild aus einer Menge auswählen"
schon kennt. Trotzdem falsch: eine Galerie gehört einem bestimmten Kunden, und
ihre Bilder in fremden Angeboten zu zeigen macht aus einer Zugriffsbeschränkung
eine Veröffentlichung. Schaufensterbilder werden bewusst hochgeladen, wie auf
eine Website — diese Entscheidung trifft der Fotograf beim Hochladen und nicht
das Angebot beim Rendern.

### 5.4 Zahlungsvorlage

Hier liegt der eigentliche Bruch. `PaymentPlan` hängt an einem `Job`, nicht an
einer `Quote`, `total` ist autoritativ, und ein Constraint-Trigger in der
Datenbank weist jeden Commit ab, dessen Installments nicht auf `total`
aufgehen. `PaymentInstallment` trägt `amount` und `due_on`, aber **keinen
Titel** — die Labels „Anzahlung / 2. Rate / Restbetrag" leitet
`PaymentPlanData::fromModel()` aus `sort_order` ab.

Nichts davon soll sich ändern. Was fehlt, ist die **Vorlage davor**: eine neue
Tabelle an `Quote`/`Package`, aus der `CreatePaymentPlan` beim Buchen die
Installments materialisiert.

`quote_payment_stages` / `package_payment_stages`:

| Feld | Typ | Wofür |
|---|---|---|
| `percent` | int | Anteil in Prozent. |
| `label` | string | Freitext, was der Kunde liest („Bei der Zusage"). |
| `minimum` | int, minor units, default 0 | Mindestbetrag; 0 heißt keiner. |
| `sort_order` | int | Reihenfolge. |

Kein Anker-Enum. `ShootKind` kennt Verlobungsshooting, Hochzeitstag, Feier,
Portrait und Sonstige — eine feste Liste von Zahlungsmomenten würde für die
Hälfte davon falsche Wörter erzwingen. Freitext, und der Fotograf schreibt hin,
was stimmt.

**`PaymentInstallment` braucht dafür ein `title`**, weil `label` client-facing
ist und die heutige Ableitung aus `sort_order` es nicht tragen kann. Das ist die
einzige Änderung an den bestehenden Payment-Modellen.

### 5.5 Die Verteilungsregel

Prozent, aber nie unter dem Mindestbetrag. Was frühere Stufen dadurch mehr
nehmen, fehlt der letzten — sie trägt immer den Rest. Eine Stufe, für die nichts
übrig bleibt, entfällt.

```php
$rest = $total;

foreach ($stages as $i => $stage) {
    $amount = $i === $stages->count() - 1
        ? max(0, $rest)
        : min(max($this->round10($total * $stage->percent / 100), $stage->minimum), max(0, $rest));

    $rest -= $amount;
}
```

Zwei Fälle, an denen sie hängt und die als Test taugen:

- 2.150 € bei 25/50/25 mit 800 € Mindestbetrag auf Stufe eins →
  **800 / 1.080 / 270**, Summe stimmt. Der Prozentsatz hätte 540 ergeben.
- 750 € (Mindestbuchung, nichts dazu) → **eine** Stufe über 750 €. Der
  Mindestbetrag übersteigt das Angebot, die anderen beiden entfallen.

Der zweite Fall ist der Grund, warum die Regel überhaupt eine Funktion ist und
kein Dreisatz an der Stelle, an der die Summe gerendert wird: sie darf niemals
Installments erzeugen, die den DB-Trigger reißen.

## 6. AcceptQuote nimmt eine Auswahl entgegen

`AcceptQuote` schreibt heute `accepted_at`, `accepted_ip`,
`accepted_by_contact_id` und schaltet den Status. Es bekommt einen zweiten
Auftrag: die Konfiguration des Kunden festschreiben.

- `hours` auf den Wert des Reglers, geprüft gegen `min_hours`/`max_hours`.
- `selected` je `QuoteItem` auf das, was der Kunde geschaltet hat, geprüft:
  eine Zeile mit `selectable = false` ist nicht schaltbar.
- `total` aus beidem berechnen — dies ist die Stelle, an der die Quote von einer
  Konfiguration zu einem Betrag wird.

Alles in derselben Transaktion wie die Zusage. Der `updating`-Guard in
`Quote::booted()` erlaubt heute genau zwei Übergänge ab `sent`; dieser Schreib
gehört zu einem davon und muss dort namentlich zugelassen werden, statt den
Guard aufzuweichen.

Die Prüfung gehört in die Action, nicht ins Portal. Was das Frontend schickt,
ist ein Vorschlag; `hours` außerhalb des Bereichs oder eine nicht schaltbare
Zeile sind eine Zurückweisung, kein stilles Zurechtbiegen — dieselbe Haltung,
die `EnsurePortalCapability` beim `accept_quotes`-Schalter schon hat.

## 7. Was daran hängt

| Stelle | Heute | Danach |
|---|---|---|
| `ReadInquiryColumn` | `total` je Quote | vor der Zusage die Spanne, danach `total` |
| `RenderQuotePdf` | Positionen und Summe | Aufstellung wie in §3.2; ein nicht angenommenes Angebot druckt die Spanne |
| `CreatePaymentPlan` | Beträge und Fälligkeiten von Hand | materialisiert aus den Stufen nach §5.5 |
| `QuoteAcceptanceRate` | unverändert | unverändert |

`due_on` bleibt offen: eine Stufe trägt einen Text, kein Datum.
`CreatePaymentPlan` läuft beim Buchen, also mit einem `Job` und dessen
Terminen — die Fälligkeiten dort zu setzen ist Teil dieser Action und keine
Eigenschaft der Vorlage.

## 8. Tests

Pest, `apps/studio/tests`, `-e APP_SURFACE=admin` auf dem Container.

- Die Verteilungsregel mit beiden Fällen aus §5.5, gegen die Summe geprüft.
- `AcceptQuote` mit `hours` außerhalb `min_hours`/`max_hours` → Zurückweisung.
- `AcceptQuote` mit einer Zeile, die `selectable = false` trägt → Zurückweisung.
- `AcceptQuote` mit gültiger Auswahl → `total` entspricht
  `hours * hourly_rate` plus gewählte Zeilen.
- Ein Angebot mit `hourly_rate = null` rendert unverändert die Positionsliste.

Vitest für die Portal-Komponente: der Regler ändert Bilderzahl und Summe, ein
Baustein schaltet um, die Stufen summieren auf die Gesamtsumme.

## 9. Offene Punkte

- **Zwei Zahlen im Entwurf haben keine Quelle**: 150 € für den Raw Dump und
  40 Bilder je Stunde. Beide sind Beispielwerte aus dem Mockup. `images_per_hour`
  ist ein Feld, das gepflegt wird; der Bausteinpreis kommt aus dem Katalog. In
  gedruckter oder gesendeter Fassung darf keine der beiden stehen bleiben.
- **Fahrtkosten** stehen im Entwurf als „Fahrt in Norddeutschland" unter „ohne
  Aufpreis". Ein Reisegebiet als Feld existiert nicht. Solange es das nicht gibt,
  ist die Zeile Freitext wie die anderen — sie behauptet dann etwas, das keine
  Einstellung deckt, und das ist der Grund, sie hier zu notieren.
- **Der Bausteinpreis ist fix.** Ein Baustein, dessen Preis von den Stunden
  abhängt, ist nicht vorgesehen. Kommt der Fall, ist er eine eigene Runde.
