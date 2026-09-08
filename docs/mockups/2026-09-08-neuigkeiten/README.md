# Neuigkeiten — design reference

This is a design mockup created in a visual design tool (an appifact
design canvas), exported as a standalone page. Treat it as a REFERENCE
MOCKUP, not production code: the markup and inline styles carry the
design's precise values — colors, font sizes, spacing, radii, shadows,
layout — which an implementation should replicate faithfully in its own
components and styling system rather than copy wholesale.

Canvas: https://claude.ai/code/artifact/28837ea9-cb16-48fb-ae0a-fb3bc3db41a2

## Contents

- `Main.dc.html` — `/neuigkeiten` am Schreibtisch. Die Filterpillen und
  „Alles aus 0.9.x" sind bedienbar; die Sidebar trägt unten die Version
  mit dem Punkt. Der Schalter `update` oben am Artboard nimmt den Punkt
  weg.
- `Mobil.dc.html` — derselbe Screen auf 390px, mit der Tab-Leiste des
  Telefons. Dieselben Filter, dieselben Daten.
- `Marker.dc.html` — die Zustände einzeln: Versionszeile über der
  Profilzeile (aktuell, neue Version da, offen), die fünf Markierungen,
  die Filterpillen.
- `Quelle.dc.html` — kein Screen, sondern der Weg: Tag im Repo, Dienst,
  Ping, Cache, Seite. Daneben die Nutzlast und wie ein Commit zur Zeile
  wird.
- `support.js`, `vendor/react*.js` — the runtime that renders the
  component in a browser; not part of the design.

## Viewing

Serve the folder (e.g. `python3 -m http.server`) and open `Main.dc.html`;
some browsers block the scripts over file://.
