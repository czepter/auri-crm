# Neuigkeiten — design

**Date:** 2026-09-08
**Status:** gebaut auf `feat/neuigkeiten-announcement`. Das Issue trägt den Scope.

---

## 1. Warum das existiert

Das Studio wird laufend deployed, und wer davor sitzt, erfährt nichts davon.
Neun Versionen liegen inzwischen in `release-log` — 0.1.0 bis 0.9.2, 95
kuratierte Einträge — und keine davon ist in der Anwendung sichtbar. Ein Fotograf, dem
die Angebotsseite plötzlich anders aussieht, hat keine Stelle, an der steht
warum.

`release-log` steht bereits: ein Node-Server, der `releases/<version>.json`
ausliefert, dazu `bin/from-git.mjs`, das diese Dateien aus den Tags des
Portal-Repos schreibt. Was fehlte, war die Seite im Studio, die ihn liest, und
die Version, an der sie erkennt, ob es etwas Neues gibt — beides steht jetzt
(§6).

## 2. Scope

**Drin:** ein Feed `/neuigkeiten` mit allen neun Releases, je Zeile Nummer,
Datum, `headline` und die Zähler je Gruppe; eine Detailseite
`/neuigkeiten/<version>` mit Bild, `headline` und allen Einträgen des Releases
nach Gruppen, jede Zeile mit ihrer Beschreibung zum Aufklappen; die drei
Gruppen, die release-log liefert (`feat` → Neu, `perf` → Änderungen, `fix` →
Behoben); die installierte Version unten in der Sidebar über der Profilzeile,
mit einem Punkt daneben, wenn `latest` höher ist; der tägliche Ping und der
Cache dahinter.

**Draußen, benannt damit die Auslassungen Entscheidungen sind:** ein Overlay
beim ersten Login nach einem Update (der Punkt ist der Hinweis, ein Modal
unterbricht die Arbeit); gelesen/ungelesen je Nutzer (der Punkt hängt an
`latest` gegen `config('app.version')` — das ist keine Nutzereigenschaft und
braucht keine Spalte); ein Auslöser für das Update selbst (Deployment läuft
über Coolify); Suche über die Einträge; Commit-Hashes, PR- und Issue-Nummern im
Studio (sie stehen in den Daten, aber ein Fotograf klickt sie nicht); ein
RSS- oder Mail-Kanal; Paginierung im Feed (neun Releases passen auf einen
Screen, `/releases` kann sie später liefern).

Die sechs Versionen ohne Tag (0.1.0–0.6.0, aus `cutoffs.json`) stehen im Feed
wie die anderen, ohne eigene Kennzeichnung.

## 3. Die Screens

Die Bilder sind aus den Artboard-Quellen gerendert und liegen unter
`docs/screenshots/`; die Quellen selbst unter
`docs/mockups/2026-09-08-neuigkeiten/`.

| Bild | Artboard | Was es zeigt |
|---|---|---|
| `docs/screenshots/2026-09-08-neuigkeiten-studio.png` | Main | der Feed: neun Releases, Zähler je Gruppe, Version mit Punkt unten in der Sidebar |
| `docs/screenshots/2026-09-08-neuigkeiten-release.png` | Detail | `/neuigkeiten/0.9.1`: Bild, headline, body, Filter, 7 + 4 Einträge |
| `docs/screenshots/2026-09-08-neuigkeiten-handy.png` | Mobil | der Feed auf 390px |
| `docs/screenshots/2026-09-08-neuigkeiten-zustaende.png` | Marker | Versionszeile in drei Zuständen, die Markierungen, die Filterpillen |
| `docs/screenshots/2026-09-08-neuigkeiten-dienst.png` | Quelle | der Weg aus release-log, die Endpunkte, die drei Gruppen |

Canvas: https://claude.ai/code/artifact/28837ea9-cb16-48fb-ae0a-fb3bc3db41a2

Alle Zahlen, Überschriften und Einträge in den Entwürfen stammen aus dem
laufenden Dienst. Nichts davon ist geschrieben.

## 4. Der Weg vom Tag in den Screen

    Tag im Repo → bin/from-git.mjs → releases/<version>.json → release-log
                → releases:fetch (Scheduler, täglich 04:00) → Cache → /neuigkeiten

Der Screen liest ausschließlich den Cache. Antwortet release-log nicht, steht
der letzte Stand da — kein Ladezustand, kein Fehlerbild, kein Ping zur Laufzeit.

Gespeichert wird ohne Ablauf (`Cache::forever`), aufgefrischt vom täglichen
Lauf. Eine Lebensdauer von 24 Stunden neben einem täglichen Lauf wäre dasselbe
Fenster von der anderen Seite: der erste gescheiterte Lauf leert den Screen.
Veraltet ist hier hinnehmbar, leer nicht.

Die Instanz steht unter
`https://uew53y9iuh4kievlxtcshuyc.coolify3.czpt.de` — vorerst, deshalb liest
`config/services.php` sie aus `RELEASE_LOG_URL` und nicht aus einer Konstante.

Was das Studio holt:

| Endpunkt | Antwort |
|---|---|
| `GET /versions` | `latest` und je Release Version, Datum, `headline`, `url`. Der Update-Check. |
| `GET /releases?page=1` | Der Feed. Je Release nur die **Zähler** je Gruppe, nicht die Einträge. |
| `GET /releases/<version>` | Ein Release mit allen Einträgen, in `sections` nach Gruppe sortiert. |
| `GET /media/<datei>` | Das Bild des Releases. Drei von neun haben eins. |
| `GET /health` | Für Coolify. |

`config('app.version')` ist die installierte Seite des Vergleichs. Sie ist nur
auf einem Release eine Zahl; ein Branch-Build trägt `dev`. Wer die beiden
vergleicht, muss deshalb zuerst auf Semver prüfen: `version_compare('dev',
'0.9.2', '<')` ist `true`, ein Dev-Build würde sich sonst als veraltet melden.

## 5. Was Git schreibt und was von Hand kommt

`bin/from-git.mjs` sagt es im Kopf selbst: alles Faktische liest es aus git,
`headline`, `body` und `image` sind Handarbeit und überleben jeden Lauf.

Aus git kommen die Fakten: `version`, `tag`, `date`, `commits` und je Eintrag
`type`, `pr`, `issues`, `commit`, `date`. `feat`, `fix` und `perf` zählen;
`docs`, `ci`, `chore` und `test` fallen raus.

Von Hand kommt alles, was gelesen wird: `headline`, `body`, `image` je Release,
und `title` und `description` je Eintrag. Der Dienst führt inzwischen 95
Einträge statt der 514 Commits, die dahinterstehen — mehrere Commits an
derselben Sache stehen als eine Zeile, in der Sprache des Fotografen
(„Download-Link in der Fertigmeldung lief nie ab").

Das Studio schreibt nichts davon und formatiert nichts um. Es zeigt, was der
Dienst liefert.

## 6. Teile

| Teil | Was es tut | Status |
|---|---|---|
| `release-log` (Node, `server.mjs`) | liefert `/versions`, `/releases`, `/releases/<v>`, `/media/` | **steht**, deployed |
| `bin/from-git.mjs` | schreibt `releases/<version>.json` aus den Tags | **steht** |
| `releases:fetch` (`App\Releases\Console\Commands\FetchReleasesCommand`) | holt und legt in den Cache, täglich 04:00 | **steht** |
| `App\Releases\Support\ReleaseLog` | der einzige Ort, der mit dem Dienst spricht | **steht** |
| `GET /neuigkeiten` und `/neuigkeiten/<version>` | ein Controller, zwei Inertia-Seiten | **steht** |
| `APP_VERSION` als Build-Arg | `release-image.yml` → `docker/app/Dockerfile` | **steht** |
| `config('app.version')` | steht in der Sidebar; unter `latest` heißt gelber Punkt | **steht** |

Keine Tabelle, keine Migration.

Die Version kommt aus dem Image, nicht aus dem Deployment — dieselbe Begründung,
die im Dockerfile schon über `SENTRY_RELEASE` steht. `release-image.yml` reicht
`github.event.release.tag_name || github.ref_name` als Build-Arg durch, das
Dockerfile backt es als `ENV`, `config/app.php` streift das führende `v` vor
einer Ziffer ab. `docker-compose.coolify.yml` bekommt nichts: es zieht einen
festen Image-Tag, also sind gebackene Version und laufendes Image dasselbe
Artefakt. `docker/app/entrypoint.sh` ruft bewusst kein `config:cache` auf, die
Config wird pro Request geparst — die ENV steht zur Laufzeit bereit.

`docker-compose.coolify.yml` trägt dafür nichts. Was es trägt, ist
`RELEASE_LOG_URL`, und zwar bei `admin` und `scheduler`: der eine liest den
Cache, der andere schreibt ihn. Die anderen Surfaces haben keinen Screen dafür.

## 7. Offen

- **Bilder gibt es für zwei von neun Releases.** Die anderen sieben zeichnen
  die Detailseite ohne. Das ist kein Fehler, aber sieben Screenshots wären
  besser als sieben Leerstellen.
- **Sechs Releases tragen keine Behoben-Gruppe.** Der Dienst führt dort nur
  Neuerungen. Ob die Fehler dieser Wochen fehlen oder bewusst nicht geführt
  werden, entscheidet release-log, nicht das Studio.
- **Farbe des Punkts.** Aktuell `--due` (#8A6212), das systemeigene „steht
  offen". Bei 6px liest es als Gold. Ein reines Gelb hat die Palette nicht.
