# Angebot: zwei Modi und ein neuer Editor — design

**Date:** 2026-09-12
**Status:** umgesetzt (2026-09-12). Der Entwurf liegt als Design-Canvas („Angebot · Klassisch / Auswahl"), die Umsetzung steht in `docs/superpowers/plans/2026-09-12-angebot-modi-und-editor.md`.
**Baut auf:** `2026-09-06-angebot-regler-design.md` (Regler, Bausteine, Stufen)

---

## 1. Warum das existiert

Seit dem Regler kann das Studio nur noch ein Auswahlangebot schreiben. Der
Grund steht in `QuoteWorkspace.vue`: `QuoteSliderEditor` rendert immer, und
`QuoteTable` nur, solange `hourlyRate` leer ist. Die Weiche zwischen den zwei
Angebotsarten ist ein Feld, das der Fotograf ausfüllt oder nicht — sichtbar
sind dabei beide Editoren übereinander, und welche Art gerade entsteht,
verrät kein Etikett.

Das trifft den Alltag: nicht jeder Auftrag ist ein Regler. Eine
Erstkommunion, ein Firmentermin, ein nachträglich verkauftes Album sind ein
Preis und eine Liste. Diese Spec macht die Art zu einer Entscheidung mit
einem Schalter und räumt den Editor dabei auf, weil derselbe Screen sonst
zwei Formularsprachen nebeneinander führt.

## 2. Scope

**Drin:** `pricing_mode` als eigenes Feld mit Umschalter im Editor; die
beiden Positionslisten zu einer zusammengeführt; Menge und Rabatt je Zeile in
beiden Modi; Empfänger mit Anschrift am Angebot; Angebotsnummer schon beim
Anlegen; Zahlungsstufen und Gültigkeit für beide Modi; Titel und Beschreibung
für die Zeitzeile; die Vorauswahl-Stunden im Editor; Validierung der
Zeitfelder serverseitig; der Editor als Kartenspalte mit Vorschau daneben;
Download jederzeit, Senden als geteilter Knopf mit „Link kopieren" und
„Versendet".

**Draußen, benannt damit die Auslassungen Entscheidungen sind:** Einheit und
Steuersatz je Zeile (die Studios hier fahren §19, und eine Einheit neben
„Menge" ist eine Rechnung, kein Angebot); ein Angebot mit mehreren Währungen;
Zahlung über einen externen Anbieter; ein Entwurfs-PDF mit Wasserzeichen (die
Nummer steht ab dem Anlegen fest, damit gibt es kein zweites Dokument);
Vorlagen je Angebotsart (`Package` deckt das ab); eine zweite Ansicht für
schmale Fenster jenseits des Umbruchs, den §7 beschreibt.

## 3. Die zwei Arten

| | Klassisch | Auswahl |
|---|---|---|
| Preis | Summe der Positionen | Stunden × Satz plus gewählte Positionen |
| Kunde entscheidet | annehmen oder nicht | Stunden am Regler, Positionen im Portal |
| Zeitkarte | nicht vorhanden | Satz, Ab, Bis, Vorauswahl, Bilder je Stunde |
| Spanne | eine Summe | Kleinstes, Vorauswahl, Größtes |

Beide teilen alles andere: Empfänger, Positionen, Zahlung, Gültigkeit, Texte,
Vorschau, Versand.

## 4. Entscheidungen

**4.1 `pricing_mode` statt Weiche über `hourly_rate`.** Ein Angebot ohne
Stundensatz ist heute zwangsläufig klassisch, ein Auswahlangebot mit noch
leerem Satz gibt es nicht. Die Spalte trennt Art und Inhalt; der Umschalter
sitzt über den Karten, weil er entscheidet, welche Karten es gibt.

**4.2 Eine Positionsliste.** „Ohne Aufpreis" und „Bausteine" waren zwei
Listen für eine Sache. Es gibt eine Liste; der Status je Zeile steht in einer
Spalte „Im Portal": *Inklusive* (`selectable = false`), *Vorausgewählt*
(`selectable = true, selected = true`), *Wählbar* (`selectable = true,
selected = false`). Das Datenmodell ändert sich dafür nicht.

**4.3 Menge und Rabatt gelten auch im Auswahlmodus.** Heute schickt
`sliderPayload()` je Zeile `quantity: 1` und keinen Rabatt. Eine Zeile ist in
beiden Modi dasselbe, also rechnen Spanne und Portal mit Menge und
Zeilenrabatt.

**4.4 Empfänger am Angebot.** Ein Projekt hat mehrere Kontakte mit Rollen; das
Angebot braucht einen davon und eine Anschrift, die der Brief trägt. Am
Angebot stehen `recipient_contact_id` und eine Kopie aus `street`,
`postcode`, `city`. Kopie, nicht Verweis: zieht der Kunde um, bleibt das
verschickte Angebot, was es war.

Vorbelegt ist der Kontakt mit der Rolle `payer`, sonst `primary`. Für die
Anschrift gelten drei Fälle:

| Kontakt | Angebot | Kontakt danach |
|---|---|---|
| hat keine Anschrift | speichert das Getippte | bekommt dieselbe Anschrift |
| gleiche Anschrift | speichert sie | bleibt |
| abweichend | speichert die abweichende | bleibt, bis „Beim Kontakt speichern" gedrückt wird |

**4.5 Jedes Angebot hat seine Nummer.** Vergeben beim Anlegen, nicht in
`SendQuote`. Ein Angebot ohne Nummer ist ein Dokument, das man herunterladen
und verschicken kann, ohne es benennen zu können. Lücken durch verworfene
Entwürfe sind in Ordnung; die Nummernkreise für Rechnungen bleiben unberührt.

**4.6 Download immer, Senden geteilt.** Ein Knopf lädt das PDF, jederzeit.
Der Sendeknopf ist geteilt wie bei Pixieset: die Fläche schickt die Mail
(`SendQuote`), das Menü dahinter bietet *Link kopieren* (Portal-Adresse für
WhatsApp) und *Versendet* (Status setzen, ohne Mail). Beide Wege enden im
selben Zustand; der Weg wird am Angebot vermerkt, damit die Kopfzeile sagen
kann, wie es rausging.

**4.7 Zahlung und Gültigkeit sind eine Karte** und gelten in beiden Modi. Die
Stufen rechnen gegen die Summe des jeweiligen Modus.

**4.8 Die Zeitzeile bekommt Titel und Beschreibung.** `RenderQuotePdf`
schreibt heute „6 Stunden", das Portal über `quotes.hoursCount` dasselbe. Das
ist keine Leistung, das ist eine Einheit. Zwei neue Felder tragen den Namen
(„Hochzeitsreportage") und die Beschreibung („Trauung, Empfang,
Paarshooting").

**4.9 Die Bilderzahl ist eine Schätzung und hängt an der Vorauswahl.** Auf
der Zeile steht „ca. 320 bearbeitete Bilder" für die vorgewählten Stunden,
nicht die Spanne über den ganzen Regler. Die Spanne steht in den drei
Kacheln, wo sie hingehört. `images_per_hour` ist optional; ist es leer,
entfällt die Angabe.

**4.10 Die Vorauswahl ist ein Feld.** `quotes.hours` existiert und wird heute
erst beim Annehmen im Portal geschrieben; das Portal startet sonst bei
`min_hours`. Der Editor bekommt das Feld, damit der Regler dort startet, wo
das Studio ihn haben will.

**4.11 Validierung serverseitig.** `hourly_rate > 0`, `min_hours ≥ 1`,
`max_hours ≥ min_hours`, `hours` zwischen beiden, `images_per_hour` optional
und wenn gesetzt `≥ 1` — im Modus `choice`. Der Editor zeigt dieselben
Grenzen am Feld.

## 5. Datenmodell

Alles auf `quotes`, eine Migration:

| Spalte | Typ | Warum |
|---|---|---|
| `pricing_mode` | string, default `classic` | §4.1 |
| `recipient_contact_id` | FK auf `contacts`, nullable | §4.4 |
| `recipient_street` | string, nullable | §4.4 |
| `recipient_postcode` | string, nullable | §4.4 |
| `recipient_city` | string, nullable | §4.4 |
| `time_title` | string, nullable | §4.8 |
| `time_description` | string, nullable | §4.8 |
| `sent_channel` | string, nullable (`mail`, `self`) | §4.6 |

Backfill: `pricing_mode = 'choice'` überall dort, wo `hourly_rate` nicht null
ist. Bestehende Entwürfe ohne Nummer bekommen keine nachgereicht — sie
erhalten eine beim nächsten Speichern nicht, sondern behalten `null`; nur neu
angelegte Angebote laufen über §4.5. Das ist billiger als eine Wanderung
durch den Nummernkreis und betrifft eine Handvoll Zeilen.

## 6. Screens

Der Canvas ist die Quelle für Maße, Reihenfolge und Kopie. Was er zeigt:

- Kopfzeile: Nummer, Status, Datum, darunter der Umschalter `Klassisch | Auswahl`.
- Karten: **Empfänger** (Kontaktpillen, Anschrift), **Zeit** (nur `choice`),
  **Positionen**, **Konditionen** (Gültig bis, Zahlung), **Text**.
- Geschlossene Karten tragen eine Zusammenfassung in der Kopfzeile.
- Eine Position ist eine Karte: Titel, Beschreibung, Menge, Preis, Rabatt,
  Im Portal, Betrag. Offen ist immer höchstens eine.
- Rechts die Vorschau als A4-Blatt, dieselbe Kette wie das PDF.

## 7. Vorschau

`DocumentPreviewPane.vue` rendert das für Verträge bereits: Blatt 794×1123 px
bei 38 px Rand, Serverantwort über einen Endpoint, lokal gezeichneter
Zwischenstand während des Tippens. Das Angebot bekommt denselben Weg, gespeist
aus `RenderQuotePdf`s Kette (Vorlage → `MergeFieldResolver` →
`DocumentRenderer`), nur ohne Gotenberg: der Endpoint gibt HTML zurück.

Die Spalte steht ab 1180 px Fensterbreite neben dem Editor. Darunter entfällt
sie, und die Vorschau liegt hinter einem Knopf in der Kopfzeile.

## 8. Offene Punkte

**Annahme ohne Mail.** Wer über WhatsApp schickt, gibt den Portal-Link aus dem
Menü mit. Tut er das nicht, kommt die Zusage als Nachricht und der Status
wird von Hand gesetzt. Ein „Angenommen"-Knopf für diesen Fall ist nicht Teil
dieser Spec.

**USt.-Schnappschuss.** `SendQuote` ruft `snapshotVat()`. „Versendet" muss
dasselbe tun, sonst steht ein verschicktes Angebot ohne Steuerstand da.
Deshalb teilen sich beide Wege eine Aktion, und nur die Mail hängt am Ende
des einen.
