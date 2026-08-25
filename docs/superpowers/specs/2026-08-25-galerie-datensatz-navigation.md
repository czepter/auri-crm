# Galerie-Datensatz: Navigation

Stand 2026-08-25. Ändert Entscheidung 1 und 9 aus
[2026-08-23-galerie-verwaltung-redesign-design.md](2026-08-23-galerie-verwaltung-redesign-design.md).
Entwurf: `mockups/redesign/crm-galerie-record.html`.

## Anlass

Entscheidung 1 hat die schmale Schiene mit vier Glyphen `▦ ✎ ⚙ ◷` aus den
Pixieset-Screenshots übernommen. Gebaut und im Browser gefahren sah sie aus wie
eine schlechte Kopie davon — und sie widerspricht dem Haus: das Projekt trägt
seit `ProjectShell.vue` eine beschriftete 150px-Spalte, und der Kommentar dort
lehnt einen Streifen über dem Inhalt ausdrücklich ab, weil Einträge mit einem
Zähler daneben eine linke Kante zum Ausrichten brauchen.

Reiter kommen nicht in Frage: die Anwendung hat nirgends welche.

## Entscheidungen

| # | Frage | Entscheidung |
|---|---|---|
| 39 | Aufbau des Records | Die Spalte aus `ProjectShell.vue`, 178px statt 150px (das Titelbild braucht die Breite). Beschriftete Einträge mit Zähler rechts: `Bilder 220 · Gestaltung · Einstellungen · Aktivität 41`. Keine Glyphen, kein Streifen |
| 40 | Titelbild | Über den Einträgen in der Spalte, volle Spaltenbreite, 4:3. Kein Navigationseintrag — es ist das Gesicht des Datensatzes, dieselbe Stelle, an der ein Kontakt seinen Avatar trägt. Steht in allen vier Bereichen |
| 41 | Leeres Titelbild | Ein gestrichelter Platz in der Form, die das Bild bekommt, mit eigener Aktion *Bild wählen*. Kein Satz darüber, dass keins gesetzt ist, und kein Wort im Statuschip — sonst steht dasselbe zweimal da |
| 42 | Sets | Eingerückt unter *Bilder*, mit Zähler und `⠿`-Griff zum Umsortieren. Kein fünfter Bereich: sie sind die Gliederung des ersten, und eine zweite Liste darunter wären zwei Navigationen in einer Spalte |
| 43 | Aufklappen der Sets | Offen, solange *Bilder* aktiv ist, sonst zu. Kein Zustand zum Merken, kein Griff zum Klappen |
| 44 | `Alle` | Erst ab dem zweiten Set. Bei einem Set steht dort nur dieses eine — `Alle` wäre eine Zeile, die dasselbe zeigt |
| 45 | Einstellungen | Behält seine eigene beschriftete Unterspalte (`.subnav`, wie `admin/pages/Settings`), rechts vom Datensatz. Zwei Spalten, weil beide etwas tragen: links die Bereiche des Datensatzes, daneben die der Einstellungen mit ihrem Zustand |
| 46 | Schmale Schirme | Ab 900px legt sich die Spalte hin und scrollt seitwärts, Sets in einer zweiten Zeile darunter. Damit fällt Entscheidung 9 („kein responsiver Umbau") für den Record weg — die Regel steht wörtlich in `ProjectShell.vue` und gilt für jeden Datensatz |

Zu 43: die Alternative wäre ein gemerkter Zustand pro Nutzer und Galerie
gewesen. Kostet eine Spalte, einen Schreibweg und eine Frage bei jedem neuen
Bereich, und beantwortet eine, die niemand stellt — wer bei den Einstellungen
arbeitet, sucht keine Sets.

Zu 46: Preis ist, dass die Set-Liste auf dem Telefon nicht mehr als Baum
lesbar ist. Der Einzug ist bei 390px kein Signal mehr; die Sets stehen dann als
eigene Zeile unter den vier Bereichen.

## Betroffene Stellen

| Datei | |
|---|---|
| `admin/components/gallery/GalleryRail.vue` | wird `GalleryNav.vue`: Titelbild, vier beschriftete Einträge, Sets unter *Bilder* |
| `admin/pages/Galleries/Record.vue` | Kopf verliert die Glyphenleiste; Statuschip trägt kein „kein Titelbild" mehr |
| `Gallery/Data/GalleryHeaderData.php` | neu, nach dem Muster von `Crm/Data/ProjectHeaderData.php:89`: `tabs` mit `key`, `label`, `href`, `count`, dazu `cover` und `sets` |
| `admin/pages/Galleries/panes/Settings.vue` | eigene `.subnav` mit Gruppen und Zustand je Zeile |

Zähler kommen wie beim Projekt aus dem Server, nicht aus dem Store: `Bilder`
zählt `assets` im Entwurf, `Aktivität` die Einträge des Blattes.

## Tests

- SSR-Test `GalleryNav`: bei einem Set kein `Alle`, ab zwei Sets eins.
- SSR-Test `GalleryNav`: ohne `cover_asset_id` der gestrichelte Platz mit
  *Bild wählen*, mit Titelbild das `<img>` — und in beiden Fällen unabhängig
  vom aktiven Bereich.
- Feature-Test `GalleryHeaderData`: `tabs[].count` stimmt mit den Entwurfs-
  Assets überein, nicht mit der veröffentlichten Fassung.

## Nicht enthalten

- Gemerkter Auf-/Zuklapp-Zustand der Sets (siehe 43)
- Sets als Baum auf dem Telefon (siehe 46)
- Gemeinsames Bauteil für Projekt- und Galerie-Spalte. Erst wenn eine dritte
  Fläche dieselbe Spalte braucht
