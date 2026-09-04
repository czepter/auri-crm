# Projekt-Rahmen: Subnav statt Tabstrip

Stand: 2026-08-22 · Branch `refactor/tailwind-only`

## Warum

Die Übersicht (`Projects/Show.vue`) und der Posteingang tragen bereits den
neuen Rahmen: `ui/page/Page` mit Rail, Band aus `BandCell`, kein Tabstrip.
Die sechs übrigen Projektseiten hängen weiter an `ProjectHeader.vue` — Kicker,
Titel, Meta-Zeile, Positions-Pille und ein Tabstrip aus reinen Textlinks,
vier gestapelte Blöcke mit rund 150px Rüstzeug über jedem Werkzeug. Dieselbe
Anwendung führt ein Projekt auf zwei Arten.

`Projects/Messages.vue` ist zusätzlich ohne jede Gestalt: die Nachrichtenliste
sind `<div>` ohne Klassen, weil die Tailwind-Migration die alten CSS-Klassen
entfernt hat, ohne Ersatz zu setzen.

## Entscheidung

Eine Werkzeugspalte je Projekt, links neben dem Inhalt, auf allen sieben
Seiten. `ProjectHeader.vue` entfällt.

Die Alternative — Rail als Navigation — scheitert an `Rail.vue:24`
(`max-[1080px]:hidden`): auf einem 1080er-Laptop verschwände der einzige Weg
zwischen den Werkzeugen. Der Tabstrip wäre der kleinste Eingriff, verlangt
aber, dass auch die Übersicht einen bekommt — und damit die Referenz kippt.

Der Preis ist benannt: die Spalte kostet dauerhaft rund 150px, und die
Anwendung trägt dann zwei Navigationsspalten nebeneinander.

## Rahmen

```
┌────────────┬──────────┬───────────────────────────┬──────────┐
│ App-Nav    │ Projekt  │ Kopf: Projekte            │ Rail     │
│ 214px      │ 150px    │       E2E Kundin  [Stand] │ 280px    │
│            │ Übersicht│                    [Akt.] │ nur auf  │
│            │ Shootings│ ─────────────────────────  │ Übersicht│
│            │ Komm.  2 │ Werkzeug                  │          │
│            │ Frageb. 1│                           │          │
└────────────┴──────────┴───────────────────────────┴──────────┘
```

- **Kopf gehört dem Objekt.** H1 ist auf allen sieben Seiten der Projektname,
  darüber der Kicker „Projekte" als Rücksprung. Welches Werkzeug offen ist,
  sagt die aktive Zeile der Spalte. Daneben die Stand-Pille, rechts die
  Projektaktion.
- **Keine Meta-Zeile über den Werkzeugen.** Haupttermin, Shooting-Zahl und
  Positionsquelle stehen im Band der Übersicht. Über dem Angebots-Editor sind
  sie Dekoration.
- **Die Rail bleibt Spur und bleibt der Übersicht vorbehalten.** Personen,
  Zahlungen, Galerien, Unterlagen. Auf den Werkzeugseiten entfällt sie: dort
  arbeitet das Werkzeug dann auf 916px statt heute 786px.
- **`Als gebucht markieren`** (`project.book`) zieht in den Seitenkopf und
  steht auf allen sieben Seiten. Heute hängt die Aktion allein in
  `ProjectHeader.vue`; von der Übersicht aus ist der Buchungsübergang derzeit
  gar nicht erreichbar.

## Bauteile

| Bauteil | Was |
| --- | --- |
| `admin/components/ProjectNav.vue` | neu: die Werkzeugspalte, isst `header.tabs` |
| `ui/page/PageHead.vue` | bestehend, nimmt Stand-Pille und Aktion im Slot |
| `ui/page/Page.vue` | bestehend, `#left` nimmt die Spalte (wie der Posteingang seine Queue) |
| `admin/components/ProjectHeader.vue` | entfällt |

`header.tabs` liefert Schlüssel, Label und Ziel bereits vom Server und ist
rechteabhängig aufgebaut (`ProjectHeaderData.php:86`). Die Spalte braucht
dafür keinen neuen Endpunkt.

## Zähler

Vier Einträge tragen eine Zahl, grau, rechtsbündig, nur wenn größer null.
Keine Signalfarbe: vier gleichzeitig leuchtende Einträge sind kein Signal.

| Eintrag | Zählt |
| --- | --- |
| Kommunikation | Fäden, deren letzte Nachricht vom Kontakt kam (`Thread::latestMessage`, dieselbe Regel wie im Posteingang) |
| Fragebögen | geteilt (`shared_at`), noch ohne Antwort |
| Angebot | Angebote im Status `sent` |
| Vertrag | erzeugt, ohne Signatur |

Shootings und Übersicht bleiben ohne: das eine ist eine Bestandszahl, die im
Band steht, das andere zählt eine Liste, die die Seite selbst zeigt.

Berechnet wird einmal in `ProjectHeaderData::fromProject()`, als
Subquery-Zählungen in einem Zug. Nicht je Controller — sonst driften die
Zahlen zwischen den sieben Seiten.

## Seiten

Keine Seite geht in einer anderen auf. Jede trägt Werkzeuge, die keine
Sektion aufnimmt.

| Seite | Änderung |
| --- | --- |
| Übersicht | Spalte links, erbt `project.book`, Rail bleibt; die Kopf-Links „Angebot" und „Shootings" entfallen, die Spalte führt dorthin |
| Shootings | Rahmentausch |
| Angebot | Rahmentausch |
| Fragebögen | Rahmentausch |
| Vertrag | Rahmentausch |
| Kommunikation | Rahmentausch **und** neuer Verlauf |

Der Umzug der Kommunikation in den Posteingang wäre Funktionsverlust, kein
Umzug: `Messages.vue` kann Mailvorlagen einsetzen und terminiert senden
inklusive Stornieren, der Posteingang kann beides nicht.

### Kommunikation

Der Verlauf folgt dem Posteingang, nicht dem Portal: Absenderzeile, Zeit,
Fließtext, eigene und fremde Nachricht über Grundton und Einzug getrennt.
Keine Sprechblasen — die bleiben der Kundenseite vorbehalten. Zwei Muster für
dieselbe Unterhaltung im selben Produkt ist genau der Bruch, den diese Arbeit
abstellt.

Erhalten bleiben: Vorlagen-Auswahl, terminiertes Senden, Stornieren einer
geplanten Nachricht, Hinweis auf Vorlagen mit unbekannten Feldern.

## Verhalten in der Breite

- **ab 1080px**: App-Nav, Spalte, Inhalt, Rail (nur Übersicht)
- **unter 1080px**: Rail entfällt wie bisher, die Spalte bleibt stehen
- **unter 640px**: die Spalte klappt in eine waagerecht scrollbare Zeile
  unter dem Kopf

Die Spalte darf nicht denselben Weg gehen wie die Rail. Sie ist Navigation,
keine Spur.

## Sprache

Die Tab-Label stehen als deutsche Zeichenketten im PHP
(`ProjectHeaderData.php:88`). Sie werden auf Übersetzungsschlüssel gedreht,
das Vue übersetzt. Sonst ist die neue Spalte die einzige Navigation im
Produkt ohne Übersetzung.

## Prüfung

- `tests/Feature/Crm/ProjectScreenTest.php:44` prüft die Tab-Schlüssel und
  bleibt gültig; die Zeile mit den Labels zieht auf die Schlüssel um.
- Ein Pest-Test je Zähler-Definition: er fällt, wenn „wartet auf Antwort",
  „geteilt ohne Antwort", „versendet" oder „unsigniert" verrutscht.
- `tests/e2e/visual.spec.ts` hält 17 Screenshot-Vergleiche. Sie werden am Ende
  in einem Zug neu aufgenommen, nicht währenddessen: der Seeder sät relativ zu
  heute, eine Baseline von gestern ist ohnehin falsch.

## Nicht in dieser Arbeit

- Der Posteingang bekommt weder Vorlagen noch terminiertes Senden.
- Keine Sektion wandert zwischen Seiten.
- Keine Fälligkeitsfarben in der Spalte.
