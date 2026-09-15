# Zweck

Auri ersetzt Pixieset, Studio Ninja und die Werkzeuge daneben, die ein
kleines Foto-Studio bezahlt, aber nie ganz nutzt. Es soll besser sein als
ein Notion-Template und eleganter als ein Google-Drive-Ordner. Die wichtige
Arbeit ist in Minuten erledigt. Die App fängt niemanden für Tage ein.

Dieses Dokument steht über der
[Produktvision](superpowers/specs/2026-08-07-photography-client-management-vision-design.md).
Wo beide sich widersprechen, gilt dieses.

## Regeln

1. **Nur der Teil, den ein kleines Studio braucht.** Funktionsumfang wird
   am Bedarf gemessen, nicht am Vorbild. Prüffrage: welches Studio hat das
   verlangt?

2. **Wenige Einstellungen, nur wo sie Last abnehmen.** Ein Setting ist
   erlaubt, wenn es eine wiederkehrende Eingabe erspart oder Studios
   voneinander unterscheidet (Gestaltung, Anrede, Steuer). Kein Setting
   steht auf dem Weg zur ersten Anfrage. Prüffrage: was passiert, wenn das
   Setting fehlt?

3. **Keine Automatisierung zum Kunden ohne Menschen davor.** Automation
   erzeugt Aufgaben fürs Studio oder Mailentwürfe zur Freigabe. Direkt
   verschickt werden nur Systemmails, ohne die nichts funktioniert:
   Login-Link, Galerie-Download, Bestätigungscodes. Jeder Versand an einen
   Kunden hat eine Vorschau und einen Senden-Button.

4. **Schriftstücke einfach erstellen und anpassen.** Angebot, Rechnung
   und Mail kommen aus Vorlagen, die ein Studio in Minuten auf sich
   umschreibt. Der Editor bleibt ein Texteditor, kein Baukasten. Verträge
   führt Auri nicht; das angenommene Angebot mit AGB und Widerruf ist die
   Vereinbarung.

5. **Offen nach außen.** API und MCP gehören zum Produkt. Was keinen
   Konsumenten hat, fliegt raus.

## Messlatte

Stand 14. September 2026, gemessen im Browser ab Übersicht nach Login:

| Handlung | Klicks | Eingaben |
|---|---|---|
| Anfrage erfassen | 6 | 3 |
| Angebot versenden | 7 | 1 |
| Buchen | 4 | 0 |
| Vertrag versenden | 2 | 0 |
| Galerie anlegen | 3 | 1 |

Pflichtfelder vor der ersten Anfrage: 0.

Eine Änderung, die eine dieser Zahlen erhöht, braucht einen Grund im
Pull Request.
