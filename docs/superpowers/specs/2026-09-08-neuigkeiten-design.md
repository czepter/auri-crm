# Neuigkeiten — design

**Date:** 2026-09-08
**Status:** design. Das Issue trägt den Scope.

---

## 1. Warum das existiert

Das Studio wird laufend deployed, und wer davor sitzt, erfährt nichts davon.
Drei Tags stehen im Repo — `v0.9.0` (5.9.), `v0.9.1` (6.9.), `v0.9.2` (8.9.) —
und keiner von ihnen ist in der Anwendung sichtbar. Ein Fotograf, dem die
Angebotsseite plötzlich anders aussieht, hat keine Stelle, an der steht warum.

Die Commit-Historie ist der Rohstoff, aber sie ist kein Changelog: 558 Commits,
davon `docs`, `ci`, `chore`, `test`, und die `feat`-Betreffe sind für Entwickler
geschrieben („SMTP-Variablen für alle Mail-Container setzen"). Zwischen Historie
und Screen liegt eine Übersetzung, die jemand vornimmt.

## 2. Scope

**Drin:** ein Screen `/neuigkeiten`, der die Releases untereinander zeigt, je
Zeile eine Änderung mit der Marke Neu oder Behoben; ein Filter über die beiden
Marken; je Version vier Zeilen, der Rest hinter einem Schalter; die installierte
Version unten in der Sidebar über der Profilzeile, mit einem Punkt daneben, wenn
der Dienst eine höhere `latest` meldet; der Dienst selbst, der täglich gepingt
und dessen Antwort gecacht wird.

**Draußen, benannt damit die Auslassungen Entscheidungen sind:** ein Overlay beim
ersten Login nach einem Update (der Punkt ist der Hinweis, ein Modal unterbricht
die Arbeit); gelesen/ungelesen je Nutzer (der Punkt hängt an
`latest > config('app.version')` — das ist keine Nutzereigenschaft und braucht
keine Spalte); ein Auslöser für das Update selbst (Deployment läuft über Coolify,
nicht über diesen Screen); Filter nach Bereich oder Suche über die Zeilen (drei
Releases, keine Menge, die danach verlangt); ein RSS- oder Mail-Kanal für
Releases; eine Zeile je Commit (die Übersetzung ist der Punkt der Sache);
Zeilen für Versionen vor `v0.9.0`.

## 3. Die Screens

Die Bilder sind aus den Artboard-Quellen gerendert und liegen unter
`docs/screenshots/`; die Quellen selbst unter
`docs/mockups/2026-09-08-neuigkeiten/`.

| Bild | Artboard | Was es zeigt |
|---|---|---|
| `docs/screenshots/2026-09-08-neuigkeiten-studio.png` | Main | `/neuigkeiten` am Schreibtisch: Filter, drei Releases, Kappung bei vier Zeilen, Version mit Punkt unten in der Sidebar |
| `docs/screenshots/2026-09-08-neuigkeiten-handy.png` | Mobil | derselbe Screen auf 390px |
| `docs/screenshots/2026-09-08-neuigkeiten-zustaende.png` | Marker | Versionszeile in drei Zuständen, die fünf Markierungen, die Filterpillen |
| `docs/screenshots/2026-09-08-neuigkeiten-dienst.png` | Quelle | der Weg vom Tag in den Screen, die Nutzlast, wie ein Commit zur Zeile wird |

Canvas: https://claude.ai/code/artifact/28837ea9-cb16-48fb-ae0a-fb3bc3db41a2

## 4. Der Weg vom Tag in den Screen

    Tag im Repo → releases.json beim Dienst → Ping (Scheduler, täglich)
                → Cache 24 h → GET /neuigkeiten

Der Screen liest ausschließlich den Cache. Antwortet der Dienst nicht, steht der
letzte Stand da — kein Ladezustand, kein Fehlerbild, kein Ping zur Laufzeit.

Nutzlast:

```json
{
  "latest": "0.9.2",
  "releases": [
    {
      "version": "0.9.2",
      "date": "2026-09-08",
      "entries": [
        { "kind": "feat", "text": "Galerie-Einstellungen liegen bei den Galerien" },
        { "kind": "fix",  "text": "Mails blieben in der Warteschlange liegen" }
      ]
    }
  ]
}
```

Wo der Dienst liegt, ist offen (§7). Er hält ein statisches JSON; die Datei
entsteht beim Taggen, nicht zur Laufzeit.

## 5. Commit wird Zeile

1. `feat:` wird Neu, `fix:` wird Behoben. `docs`, `ci`, `chore`, `test` fallen raus.
2. Zwei Commits an derselben Sache werden eine Zeile. Beispiel aus `v0.9.2`:
   `fix: jede Mail über die mail-Queue verschicken` und `fix: SMTP-Variablen für
   alle Mail-Container setzen` ergeben zusammen „Mails blieben in der
   Warteschlange liegen".
3. Die Zeile nennt, was der Fotograf jetzt tun kann. Kein Dateiname, kein
   Klassenname, kein `Host`, kein `Rumpf`.
4. Geschrieben beim Taggen und beim Dienst abgelegt.

## 6. Teile

| Teil | Was es tut |
|---|---|
| `[RELEASE-HOST]/v1/releases.json` | statisches JSON, beim Taggen geschrieben |
| `CheckReleases` (Console-Command, Scheduler täglich) | holt und legt in den Cache |
| `Cache::remember('releases', …)` | 24 Stunden; der Screen liest nur hier |
| `GET /neuigkeiten` | ein Controller, eine Inertia-Seite |
| `config('app.version')` | steht in der Sidebar; unter `latest` heißt gelber Punkt |

Keine Tabelle, keine Migration.

## 7. Offen

- **Wo der Dienst liegt.** Eigene Subdomain mit statischem JSON, ein Bucket, oder
  die Raw-URL des Repos. Die Entscheidung ändert nichts am Screen.
- **Wer die Zeilen schreibt.** Beim Taggen von Hand, oder ein Agent, der den
  Commit-Range vorlegt und der Mensch abnimmt.
- **Farbe des Punkts.** Aktuell `--due` (#8A6212), das systemeigene „steht offen".
  Bei 6px liest es als Gold. Ein reines Gelb hat die Palette nicht.
