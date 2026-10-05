# UI-Schriftskala — design

**Date:** 2026-10-05
**Status:** design

---

## 1. Warum das existiert

Die Admin-Oberfläche ist zu klein gesetzt: Navigation 13px, Anfrage-Text 14px.
Es gibt keine Skala. `resources/js` und `resources/css` enthalten rund 1.700
feste Werte wie `text-[12.5px]` in 216 Dateien, 20 verschiedene Größen von
8,5 bis 32px, dazu Halbpixel-Werte (11,5 / 12,5 / 13,5 / 14,5), die
nebeneinander stehen und keine Stufe ergeben.

Referenz (nachgeschlagen: Primer, Atlassian, Radix Themes): 14px als
Komponenten-Standard, 16px für Lesetext, 12px als Minimum, Überschriften in
16 / 20 / 24. Abweichung: `small` ist 13px, weil 13px in der Navigation schon
zu dünn wirkte und 12px darunter liegt.

## 2. Scope

**Drin:** fünf benannte Stufen als `--text-*` in `resources/css/app.css`
(`@theme`), Ersatz aller Literale im Admin durch diese Klassen, einmal
erneuerte E2E-Baselines.

**Draußen:**
- `resources/js/portal/**` und `resources/css/gallery.css`. Kundenansicht mit
  eigener Serif-Typografie, eigene Skala wäre ein eigener Auftrag.
- `display` (32px, 7 Stellen: Dashboard-Zahlen, Auth-Seiten) bleibt ein
  Literal an Ort und Stelle.
- Schriftfamilien, Farben, Abstände.

## 3. Skala

| Klasse | Größe | Zeilenhöhe | Einsatz |
|---|---|---|---|
| `text-small` | 13px | 1.4 | Datum, Meta, Zähler, Badges, Eyebrows in Großbuchstaben |
| `text-ui` | 14px | 1.4 | Navigation, Buttons, Inputs, Menüs, Tabellen, Labels, Kartenüberschriften |
| `text-body` | 16px | 1.55 | Lesetext: Anfrage, Nachrichten, Notizen, Hilfe |
| `text-title` | 20px | 1.3 | Dialogtitel |
| `text-heading` | 24px | 1.2 | Seitentitel |

Regeln:
- Unter 13px steht im Admin kein Text mehr.
- Hierarchie entsteht durch Gewicht (400 / 500 / 600) und Farbe, nicht durch
  weitere Größen. Kartenüberschriften ("Anfrage", "Kontakte") sind `text-ui`
  mit 600.
- Navigation: `text-ui`, Gewicht 500.
- Eyebrows ("ARBEIT", "AUFTRÄGE"): `text-small`, uppercase, Laufweite 0,06em.

## 4. Umsetzung

1. `app.css`: fünf Einträge im `@theme` (`--text-small`,
   `--text-small--line-height` usw.). Tailwind erzeugt daraus die Klassen.
2. Mechanisches Mapping, Reihenfolge von groß nach klein, damit kein Wert
   doppelt ersetzt wird:

   | alt | neu |
   |---|---|
   | 22, 24, 26 | `text-heading` |
   | 17, 18, 19, 20 | `text-title` |
   | 15, 15.5, 16 | `text-body` |
   | 13.5, 14, 14.5 | `text-ui` |
   | 10 bis 13 (inkl. 10.5, 11.5, 12.5) | `text-small` |

   Ausgenommen: 32px und größer, `portal/**`, `gallery.css`, Stories
   (`*.stories.ts`).
3. Handarbeit nach dem Mapping, weil der Wert allein die Rolle nicht
   verrät: Lesetext (Anfrage, Nachrichten, Notizen, Hilfe) auf `text-body`;
   Seitentitel auf `text-heading`; Dialogtitel auf `text-title`;
   Kartenüberschriften auf `text-ui` mit 600. Die Stellen werden per Grep
   nach Komponente gesucht, nicht geraten.
4. `css/*.css`: Deklarationen `font-size: Npx` auf `var(--text-…)`.
5. Die drei `text-sm` / `text-xs` / `text-base`-Treffer prüfen und ersetzen.
6. Zeilenhöhe: Wo ein Literal `leading-[…]` neben `text-[…]` stand, bleibt die
   Zeilenhöhe der Stufe, sofern das Layout (Zeilenabstand in Listen, Buttons
   mit `leading-none`) nicht anderes verlangt.

## 5. Prüfung

- Im Browser: Projekt-Detail, Board, Posteingang, Kalender, Aufgaben,
  Einstellungen, Dialoge. Überläufe (Spalten, Badges, Sidebar-Breite,
  Buttons mit festem `px`) werden korrigiert.
- `grep -rE 'text-\[[0-9.]+px\]' resources/js --exclude-dir=portal` liefert nur
  noch die ausgenommenen `display`-Stellen.
- Typecheck und Vitest für geänderte Dateien. Kein neuer Test: reines Styling.
- E2E: `E2eSeeder` ist relativ zu `now()`; die 54 Baselines ändern sich
  ohnehin und werden einmal erneuert.

## 6. Risiken

- Größere Schrift ändert Zeilenumbrüche. Feste Breiten (`w-[…]`,
  Spaltenraster, Sidebar) können brechen. Das Browser-Review fängt das.
- Halbpixel-Werte verschwinden: Dichte-Unterschiede, die heute zufällig
  entstanden, sind danach weg. Gewollt.
