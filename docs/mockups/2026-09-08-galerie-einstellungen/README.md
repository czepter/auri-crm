# Galerie-Einstellungen — design reference

This is a design mockup created in a visual design tool (an appifact
design canvas), exported as a standalone page. Treat it as a REFERENCE
MOCKUP, not production code: the markup and inline styles carry the
design's precise values — colors, font sizes, spacing, radii, shadows,
layout — which an implementation should replicate faithfully in its own
components and styling system rather than copy wholesale.

Canvas: https://claude.ai/code/artifact/0926633a-ca1c-483d-b61e-5552df9b035d

## Contents

- `Main.dc.html` — der Einstellungsraum über `/galerien`, drei Abschnitte
  (Standardwerte, Gestaltung, Vorlagen). Die Schiene, die Regler und die
  Schalter sind bedienbar.
- `Index.dc.html` — `/galerien` mit der Einstellungen-Pille im Kopf.
- `GalleryWindow.dc.html` — die Vorschau: die Galerie im Browserfenster,
  von `Main.dc.html` zweimal eingebunden. Bekommt eine Gestaltung und vier
  Tore und zeigt Cover, Leiste und Wall daraus.
- `support.js`, `vendor/react*.js` — the runtime that renders the
  component in a browser; not part of the design.

## Viewing

Serve the folder (e.g. `python3 -m http.server`) and open `Main.dc.html`;
some browsers block the scripts over file://.
