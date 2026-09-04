# ADR-0001: Vier Leser für „was liegt an diesen Tagen", kein gemeinsames Modul

Status: entschieden, 2026-08-21

## Kontext

Vier Stellen lesen Shootings, Studioschließungen und Abwesenheiten über einen
Zeitraum:

| Stelle | Frage | Shootings |
|---|---|---|
| `Crm/Actions/CheckDateAvailability::forDates` | Kollidiert etwas mit diesen Tagen? | nur Projekte mit nicht verlorener Anfrage |
| `Crm/Http/Controllers/CalendarController` | Was steht im Monatsraster? | alle |
| `Http/Controllers/Staff/DashboardController::week` | Was hat das Studio diese Woche zu tun? | gebucht **und** für diese Rolle sichtbar |
| `Crm/Http/Controllers/LeadCaseController::week` | Was liegt um den Wunschtermin? | alle außer denen dieses Projekts |

Eine Architekturdurchsicht schlug vor, das hinter ein `ReadDays(range)` zu
ziehen: eine Abfrage, ein Eintragstyp, vier Leser.

## Entscheidung

Bleibt getrennt.

## Begründung

Die vier teilen die Tabellen, nicht die Frage. Jede filtert Shootings anders
(Anfrage-Projekte / alle / gebucht+sichtbar / alle außer diesem), und sie
ziehen unterschiedliche Quellen heran: Verfügbarkeit nimmt Schließungen und
Abwesenheiten, die Wochenleiste der Übersicht nimmt Abwesenheiten ohne
Schließungen, die Leiste am Anfrageschirm keine von beiden.

Ein gemeinsames Modul müsste Zeitraum, Shooting-Filter, Quellenauswahl und
Ausgabeform als Parameter tragen. Das ist eine breite Schnittstelle für einen
schmalen Gewinn: gelöscht würde sie keine Komplexität bündeln, sondern eine
Parameterliste verschieben. Jede der vier ist heute für sich lesbar und drei
von vier haben eigene Tests.

## Wann das neu zu prüfen ist

Wenn eine fünfte Quelle dazukommt (Feiertage waren im Gespräch) und in mehr
als einer der vier auftauchen muss. Dann teilen sie zum ersten Mal eine
Regel und nicht nur eine Tabelle.
