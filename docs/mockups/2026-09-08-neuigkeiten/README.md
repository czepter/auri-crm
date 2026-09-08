# Neuigkeiten — design reference

This is a design mockup created in a visual design tool (an appifact
design canvas), exported as a standalone page. Treat it as a REFERENCE
MOCKUP, not production code: the markup and inline styles carry the
design's precise values — colors, font sizes, spacing, radii, shadows,
layout — which an implementation should replicate faithfully in its own
components and styling system rather than copy wholesale.

Canvas: https://claude.ai/code/artifact/28837ea9-cb16-48fb-ae0a-fb3bc3db41a2

Die Zahlen, Titel und Beschreibungen stammen aus `release-log/releases/*.json`
und sind nicht erfunden. Ausnahme: die drei `headline`-Zeilen im Feed — das
Feld ist dort heute in allen neun Releases leer, und ohne es zeigt der Entwurf
seinen eigenen Platz nicht.

## Contents

- `Main.dc.html` — `/neuigkeiten`, der Feed. Neun Releases, je Zeile Nummer,
  Datum, headline und die Zähler je Gruppe. Unten in der Sidebar die
  installierte Version mit dem Punkt; der Schalter `update` nimmt ihn weg.
- `Detail.dc.html` — `/neuigkeiten/0.9.1`. Bild, headline, Filter und die 40
  Einträge des Releases nach Gruppen. Jede Zeile klappt ihre Beschreibung auf.
- `Mobil.dc.html` — der Feed auf 390px mit der Tab-Leiste des Telefons.
- `Marker.dc.html` — die Zustände einzeln: Versionszeile über der Profilzeile
  (aktuell, neue Version da, offen), die Markierungen, die Filterpillen.
- `Quelle.dc.html` — kein Screen, sondern der Weg: Tag, `from-git.mjs`,
  release-log, Ping, Cache, Studio. Dazu die Endpunkte, die drei Gruppen und
  was Git schreibt gegenüber dem, was von Hand kommt.
- `0.9.1-angebot-regler.jpg` — das Release-Bild, verkleinert aus
  `release-log/media/`.
- `support.js`, `vendor/react*.js` — the runtime that renders the
  component in a browser; not part of the design.

## Viewing

Serve the folder (e.g. `python3 -m http.server`) and open `Main.dc.html`;
some browsers block the scripts over file://.
