# Hilfe und Dokumentation im Studio — design

**Date:** 2026-09-08
**Status:** design, keine Issues gefiled — diese Spec trägt die Begründung, die
Issues tragen den Scope, sobald sie stehen.

---

## 1. Warum das existiert

Das Studio hat vierzehn Hauptscreens und achtzehn Einstellungs-Panes, und
nichts davon erklärt sich einem Inhaber, der es nicht gebaut hat. Was an
Dokumentation existiert — `docs/inbound-mail.md`, `docs/inquiry-form.md`,
`docs/architecture.md` — richtet sich an Entwickler, liegt im Repository und
ist im Produktionsimage nicht einmal vorhanden: `docker/app/Dockerfile`
kopiert ausschließlich `apps/studio`.

Die Lücke ist am größten dort, wo eine Aufgabe Werte der eigenen Instanz
braucht. Das Anfrageformular für die eigene Website ist ein Snippet, das ohne
die API-Domain nicht funktioniert. Die Inbound-Adresse wird aus
`studio_settings.inbound_secret` gemintet und steht in keinem Handbuch der
Welt. Ein Artikel mit `<DEINE_DOMAIN>` schickt den Leser zurück in die
Einstellungen zum Suchen — und meistens gibt er vorher auf.

## 2. Scope

**Drin:** `Page.vue` wird zur Seitenhülle aller Admin-Screens (§3); eine
Hilfedomäne unter `apps/studio/app/Help`; Artikel als Markdown unter
`apps/studio/resources/help`; serverseitiges Rendern mit Platzhalterersetzung
aus der laufenden Instanz, samt der zwei Umgebungswerte, die der
Admin-Prozess dafür noch nicht kennt (§4.3); eine auf- und zuklappbare Spalte
rechts, die den zum aktuellen Screen gehörenden Artikel zeigt; eine
Vollansicht unter `/hilfe`; Hilfe-Suche in der bestehenden ⌘K-Palette;
zweiundzwanzig Artikel in zwei Ebenen, in drei Phasen geschrieben.

**Draußen, benannt damit die Auslassungen Entscheidungen sind:** Hilfe im
Portal (ein Kunde besucht es drei- bis fünfmal je Auftrag; was er nicht
versteht, gehört auf den Screen oder in die Mail, nicht in einen Artikel, den
er nie sucht); eine öffentliche Doku-Domain (die Doku liegt hinter Auth, und
eine zweite Astro-Anwendung kostet ein siebtes Image in
`release-image.yml`, einen Service in `docker-compose.coolify.yml`, eine
Domain und ein Sentry-DSN, ohne dass irgendetwas davon einem eingeloggten
Inhaber nützt); Artikel, die zur Laufzeit editierbar sind (ein zweites CMS für
ein Ein-Studio-Produkt; Inhalte gehören zum Release); Screenshots in Artikeln
(sie verrotten schneller als die Doku gepflegt wird — dieselbe Erfahrung wie
mit den E2E-Baselines); Telemetrie darüber, welcher Artikel gelesen wird (ein
Einzelstudio erzeugt Zahlen, aus denen sich nichts ableiten lässt, und der
Inhaber ist der Einzige, der sie läse — er kann es auch sagen);
Pfeiltasten-Navigation in der ⌘K-Palette (die Liste ist heute reine
`<a>`-Elemente, ein Roving-Tabindex ist eigene Arbeit und kein Anhängsel);
eine zweite Sprache; ein Artikel über die Anbindung mehrerer Postfächer und
einer über API-Token für n8n (beide Features fehlen, siehe §10).

## 3. Voraussetzung: `Page.vue` wird die Seitenhülle

Die Hilfespalte braucht einen Ort, der auf jedem Screen existiert und der
weiß, ob dieser Screen bereits eine Rail hat. Heute gibt es diesen Ort nicht:
`AdminLayout` sieht die Rail nicht (sie entsteht tiefer im Baum), und
`Page.vue` — das sie sieht — steht nur auf fünf von vierundvierzig Dateien.

**44 Admin-Dateien rendern `AdminLayout`, 13 davon gehen bereits durch
`Page`. Die übrigen 29 werden migriert.** Danach ist die Hilfespalte reine
Struktur in einer Komponente, ohne Prop-Durchreichung, ohne Modulzustand,
ohne Routenliste in PHP.

### 3.1 Der vierte Slot

```
Page.vue:  [ slot left ] [ Mitte (default) ] [ slot right ] [ slot help ]
```

```html
<slot name="help"><HelpRail /></slot>
```

Benannter Slot mit Vorgabeinhalt: kein Screen muss ihn füllen, jeder Screen
kann ihn überschreiben, wenn dort einmal etwas anderes hingehört. Ohne
Vorgabe schrieben zweiundvierzig Aufrufstellen dieselbe Zeile, und die
dreiundvierzigste vergäße sie.

### 3.2 Das Migrationsrezept

Die 29 Screens haben alle dieselbe Form. `ProjectShell.vue:24` und
`Posteingang.vue:132` führen das Ziel vor: der Default-Slot nimmt **ein**
Element auf, weil `Page`s Mitte eine Flex-Zeile ist. Zwei Geschwister darin
stünden nebeneinander statt untereinander.

```html
<!-- vorher -->
<AdminLayout active="team">
  <div data-tokens>
    <PageHead … />
    …
  </div>
</AdminLayout>

<!-- nachher -->
<AdminLayout active="team">
  <Page data-tokens wide>
    <div class="min-w-0 flex-1">
      <PageHead … />
      …
    </div>
  </Page>
</AdminLayout>
```

`wide` auf **allen** 29. `AdminLayout`s `<main>` hat heute keine Breitengrenze,
diese Screens laufen also voll aus; ohne `wide` deckelte `Page` sie auf
`--container-case` (1180px) und zwölf Screens würden auf großen Bildschirmen
sichtbar schmaler. Das wäre ein Redesign, geschmuggelt in ein Doku-Projekt.
Der Deckel mag anderswo richtig sein — das ist dann eine eigene Entscheidung
mit eigenem Issue.

### 3.3 Eigener Commit

Die Migration geht in einen Commit **vor** jeder Hilfe-Zeile. 29 Templates und
ein neues Feature in einem Commit hieße, dass ein verrutschter Screen nicht
zuzuordnen ist. Sie ist der größere Teil des Risikos in diesem Projekt, nicht
die Hilfe selbst.

## 4. Ablage und Inhaltsmodell

Flach unter `apps/studio/resources/help/`, Dateiname ist der Slug:

```
apps/studio/resources/help/
  lead-anlegen.md
  anfragen-referenz.md
  mcp-claude-verbinden.md
  …
```

Flach, weil der Slug ohnehin eindeutig sein muss und `/hilfe/lead-anlegen`
dann keine Pfadlogik braucht. Die Ebene steht im Frontmatter, nicht im
Verzeichnis; das Präfix der Referenzartikel steht hinten (`anfragen`,
`anfragen-referenz`), damit beide alphabetisch beieinanderliegen.

`apps/studio/resources/` und nicht `docs/`, weil nur `apps/studio` im
Produktionsimage landet. Ein Hilfesystem, das lokal Artikel hat und in
Produktion leer ist, fällt erst beim Kunden auf.

### 4.1 Frontmatter

`symfony/yaml` liegt bereits in `apps/studio/composer.lock`.

```yaml
---
titel: Lead anlegen
beschreibung: Eine Anfrage von Hand erfassen und durch die Stufen führen.
art: aufgabe                      # aufgabe | referenz
reihenfolge: 20                   # nur bei art: aufgabe
screens: [leads.index, leads.show]
faehigkeit: manage_leads          # optional
---
```

`screens` sind **Route-Namen**. Damit ist die Zuordnung Screen → Artikel keine
zweite Tabelle an einer dritten Stelle, sondern steht im Artikel selbst; die
Rückwärtsabbildung wird daraus erzeugt. `SettingsNav.vue` trägt den Kommentar,
warum das wichtig ist: als jede Seite ihre Liste selbst hielt, zeigten fünf
von acht Seiten Panes als „bald", die längst ausgeliefert waren.

**Höchstens zwei Artikel je Route, einer je `art`** — per Wächter-Test (§9).
Ein Umschalter mit fünf Reitern in 280px ist eine Liste, die sich als
Umschalter verkleidet.

`faehigkeit` ist ein Wert des bestehenden `Capability`-Enums, keine neue
Rollenerfindung. Fehlt der Schlüssel, sieht den Artikel jeder, der den Screen
erreicht.

Ein Artikel **ohne** `screens` ist zulässig und der Normalfall für Aufgaben,
die keinen Screen haben: `mcp-claude-verbinden` und
`anfrageformular-einbinden`. Sie an den nächstliegenden Screen zu heften
(MCP an `/team`, weil dort Zugänge vergeben werden) böte dem Inhaber Hilfe an,
die sein Handeln nicht ausgelöst hat — genau die Sorte Zuordnung, die ein
Hilfesystem unglaubwürdig macht. Beide stehen auf `/hilfe` weit oben und
werden aus `erste-schritte` verlinkt.

### 4.2 Platzhalter

| Platzhalter | Quelle |
|---|---|
| `%api_url%` | `config('services.api.url')` — neu, siehe §4.3 |
| `%inbound_address%` | `StudioSettings` — die gemintete Catch-All-Adresse |
| `%mcp_url%` | `config('services.mcp.url')` — neu, siehe §4.3 |
| `%studio_name%` | `StudioSettings` |

Das Trennzeichen ist `%…%` und nicht `{{ … }}`, weil n8n-Ausdrücke wörtlich
`{{ $json.feld }}` sind. Der n8n-Artikel ist aufgeschoben (§10), aber das
Trennzeichen später zu wechseln kostet eine Migration über den ganzen Korpus.

**Sicherheitsrelevant:** Der Artikeltext stammt aus dem Repository und wird
als HTML gerendert. Die **Platzhalterwerte** stammen teilweise aus Eingaben
des Inhabers — der Studioname steckt geslugged in der Inbound-Adresse. Sie
werden beim Einsetzen escaped. Die Ersetzung passiert **nach** dem
Markdown-Rendern, auf dem fertigen HTML, sonst kann ein Wert die Struktur
brechen.

### 4.3 Zwei Werte, die der Admin-Prozess heute nicht kennt

`docker-compose.coolify.yml` gibt dem `admin`-Service `PORTAL_URL` und
`GALLERY_URL`, aber weder eine API- noch eine MCP-Domain. `config/app.php`
kennt nur `APP_URL`, und das ist auf diesem Container die Studio-Domain. Ohne
Nachrüstung liefern `%api_url%` und `%mcp_url%` in Produktion nichts — und das
trifft ausgerechnet `anfrageformular-einbinden` und `mcp-claude-verbinden`,
die beiden Artikel, wegen derer die Platzhalter überhaupt existieren.

Nachzurüsten ist:

- `API_URL` und `MCP_URL` im `admin`-Service in `docker-compose.coolify.yml`,
  aus `${SERVICE_FQDN_API}` und `${SERVICE_FQDN_MCP}` — beide Domains sind
  laut `docs/deployment.md` ohnehin zu vergeben.
  `docker/app/check-coolify-compose.php` prüft diese Datei, die Ergänzung muss
  also durch dessen Regeln.
- Zwei Einträge in `config/services.php`, neben dem bestehenden `gallery`,
  jeweils mit `APP_URL`-Fallback für den Einzelhost-Betrieb, den die Testsuite
  fährt. Dieselbe Form, die `config/portal.php` mit eigener Begründung
  vorführt.
- Beide Schlüssel in `apps/studio/.env.example`.

Ein fehlender Wert fällt auf `APP_URL` zurück und ergibt einen falschen, aber
plausiblen Host. Deshalb prüft ein Test die Ersetzung gegen einen gesetzten
Wert und nicht gegen „nicht leer" (§9).

### 4.4 Cache

Geteilt, mit unterschiedlicher Begründung je Hälfte:

- **Index** (Slug, Titel, Beschreibung, Art, Reihenfolge, Screens, Fähigkeit,
  Reintext für die Suche): gecacht. Ohne ihn liest jeder Seitenaufruf
  zweiundzwanzig Dateien, nur um zu wissen, ob es für diesen Screen einen
  Artikel gibt.
- **Artikeltext**: nicht gecacht. Eine Datei parsen ist etwa eine
  Millisekunde, ein Cache dafür ist Infrastruktur ohne Anlass — und der
  gerenderte Text trägt Werte des Mandanten, gehört also ohnehin in keinen
  geteilten Speicher.

Der Cache-Schlüssel enthält den Hash über die `filemtime` aller Hilfedateien.
`docker/app/entrypoint.sh` räumt bewusst keine Caches (siehe dort den
`ponytail:`-Kommentar zu `config:cache`), und der Store ist Redis — der
überlebt den Deploy. Ohne selbstinvalidierenden Schlüssel stünde nach einem
Release der Index des vorigen. Zweiundzwanzig `stat`-Aufrufe sind ein
Bruchteil von zweiundzwanzig Dateilesungen samt YAML.

## 5. Serverseite

Neue Domäne `apps/studio/app/Help`, gebaut wie ihre Geschwister:

```
app/Help/
  Actions/RenderHelpArticle.php     Datei lesen, Frontmatter trennen, Str::markdown, Platzhalter einsetzen
  Data/HelpArticle.php              slug, titel, art, screens, faehigkeit, html
  Data/HelpEntry.php                Index-Eintrag ohne html
  Support/HelpIndex.php             einlesen, cachen, Route-Name → Artikel auflösen, nach Fähigkeit filtern
  Support/HelpPlaceholders.php      die vier Werte, einmal, escaped
  Http/Controllers/HelpController.php
```

`league/commonmark` liegt bereits im Lock (Laravel zieht es für
Mail-Markdown), `Str::markdown()` funktioniert ohne neue Abhängigkeit.

### 5.1 Routen

In `apps/studio/routes/admin.php`, in der Staff-Auth-Gruppe:

| Route | Antwort | Zweck |
|---|---|---|
| `GET /hilfe` | Inertia | Vollansicht, beide Ebenen |
| `GET /hilfe/{slug}` | Inertia | Vollansicht eines Artikels |
| `GET /hilfe/artikel/{slug}` | JSON | Was die Spalte beim Aufklappen holt |
| `GET /hilfe/suche` | JSON | Index für die ⌘K-Palette, einmal je Seitenladung |

### 5.2 Warum die Spalte nicht über Inertia lädt

Ein `router.reload({ only: ['helpArticle'] })` würde die komplette
Controller-Aktion des aktuellen Screens erneut ausführen — sämtliche Queries
des Projektscreens, um einen Hilfetext zu holen. Inertia wertet zwar nur die
angeforderte Prop aus, der Controller-Rumpf läuft trotzdem.

Die Spalte holt deshalb `GET /hilfe/artikel/{slug}` per `fetch` und legt das
Ergebnis in eine Map im Modulzustand. Zweites Aufklappen ist ohne Netz.

Scheitert der Abruf — Netz weg, 500, Deploy mitten im Klick — steht im Panel
eine Zeile, die benennt, was nicht ging, und daneben ein Wiederholen-Knopf.
Die Zeile beruhigt nicht und erklärt nicht, warum; der Knopf ist die Antwort.

### 5.3 Was auf jedem Request geteilt wird

Genau eine Zeile in `HandleInertiaRequests::share()`:

```
'help' => [ ['slug' => …, 'titel' => …, 'art' => …], … ]   // 0 bis 2 Einträge
```

Aufgelöst aus dem gecachten Index über `$request->route()->getName()`,
gefiltert über `faehigkeit`, Aufgabe vor Referenz. Kein Dateizugriff und kein
Markdown-Parsing, solange die Spalte zu ist. Eine leere Liste heißt: für
diesen Screen gibt es keine Hilfe, der Knopf erscheint nicht.

### 5.4 Berechtigung

Ein Artikel, dessen `faehigkeit` der Nutzer nicht hat, taucht weder im Index
auf noch in `/hilfe` noch in `/hilfe/suche`, und `/hilfe/{slug}` antwortet 404
statt 403. Das ist dieselbe Regel, die `AdminLayout` auf seine Navigation
anwendet: ein Eintrag, hinter dem für diesen Nutzer nichts steht, existiert
für ihn nicht.

Kein Artikel für einen Screen ist der Normalfall und kein Fehler.

## 6. Die Hilfespalte

`HelpRail` ist `Rail.vue` mit anderem Inhalt. Die Komponente existiert bereits
und ist Zeile für Zeile das, was gebraucht wird:

```
<aside class="sticky top-0 ml-auto h-screen w-70 flex-none overflow-y-auto
              bg-sunk px-5 py-4 max-[1080px]:hidden">
```

**Kein Overlay, kein Scrim, kein Fokusfang, kein `aria-modal`.** `<aside>` ist
ein `complementary`-Landmark, und Escape schließt nicht — das Panel ist kein
Dialog, und Escape gehört dem Screen, an dem weitergearbeitet wird. Der ganze
Sinn der Spalte ist, dass die Arbeit stehenbleibt.

**Position:** ganz außen rechts, neben einer vorhandenen Seiten-Rail statt an
ihrer Stelle. Die Seiten-Rail gehört zum Datensatz und wandert mit ihm; Hilfe
gehört zur Anwendung und steht immer am selben Fleck. Was seinen Platz nie
wechselt, gehört an den Rand.

### 6.1 Zwei Schwellen

`Rail.vue` verschwindet unter 1080px. Zwei Rails nebeneinander brauchen mehr.
Gerechnet mit `nav 214`, `pl-12` und zweimal `w-70`:

| Fenster | Fallakte nutzbar |
|---|---|
| 1280 | **458px** |
| 1440 | 618px |
| 1536 | 714px |
| 1920 | 1098px |

| Situation | Hilfe rastet ein ab |
|---|---|
| Screen ohne Seiten-Rail | 1080px |
| Screen mit Seiten-Rail | 1536px |

Unter der jeweiligen Schwelle gibt es kein Panel; der Knopf wird ein `<Link>`
nach `/hilfe/{slug}`. `Page.vue` kennt `$slots.right` und entscheidet das
selbst — deshalb braucht es dafür weder Prop noch Modulzustand noch
Routenliste.

### 6.2 Zustand

Zwei `localStorage`-Schlüssel:

- `help.open` — offen oder zu.
- `help.seen` — ob die Spalte einem Menschen schon einmal gezeigt **und** von
  ihm zugeklappt wurde.

Beim ersten Besuch ist die Spalte **offen**, bis sie einmal zugeklappt wird;
danach gilt die Wahl. Ein Hilfesystem, das zugeklappt startet, wird nicht
entdeckt. `help.seen` wird erst gesetzt, wenn die Spalte wirklich sichtbar war
— sonst verschluckt ein schmales Fenster die einzige Entdeckungschance.

`apps/studio/resources/js/admin/main.ts` vergibt keine persistenten
Inertia-Layouts; jede Seite bringt `<AdminLayout>` selbst mit, das Layout
mountet bei jeder Navigation neu. Der Zustand wird deshalb synchron im
`setup()` gelesen, nicht in `onMounted`, sonst blitzt das Panel bei jedem
Seitenwechsel auf.

Geteilter Zustand in `apps/studio/resources/js/admin/lib/helpPanel.ts`,
Modulebene:

```
{ offen: boolean, ueberschrieben: string | null }
```

Angezeigter Slug ist `ueberschrieben ?? erster Eintrag der help-Prop`. Ein
Navigationswechsel setzt `ueberschrieben` zurück, sonst klebt der in der
Palette gewählte Artikel über allen folgenden Screens.

### 6.3 Umschalter

Trägt die `help`-Prop zwei Einträge (Aufgabe und Referenz), steht oben im
Panel ein Umschalter, Aufgabe zuerst: wer Hilfe öffnet, will meistens etwas
tun und nicht nachschlagen.

### 6.4 Links im Artikel

Ein rohes `<a href>` in einem `v-html`-Block macht einen vollen
Seitenladevorgang — die offene Fallakte daneben ist weg, also genau das,
wogegen die Spalte gebaut wird. Ein Klick-Listener auf dem Artikelcontainer
fängt beides ab:

- Ziel beginnt mit `/hilfe/` → nur der Panel-Inhalt wird getauscht.
- Jedes andere interne Ziel → `router.visit`. Wer auf „Einstellungen" klickt,
  will dorthin, aber innerhalb der SPA.

### 6.5 Prosa-Styles

Das Projekt hat kein `@tailwindcss/typography`. Das Artikel-HTML bekommt etwa
zwanzig Zeilen eigene Regeln für `h2 h3 p ul ol code pre a` im bestehenden
Token-System. Eine neue Abhängigkeit für sieben Elementtypen wäre der falsche
Tausch.

### 6.6 Der Knopf

Oben in der Spalte des Arbeitsbereichs, nicht in der linken Navigation — er
wechselt keine Seite, er öffnet ein Werkzeug. Dieselbe Begründung, mit der die
Suche über der Navigation steht und nicht darin. Er erscheint nur, wenn die
`help`-Prop nicht leer ist.

## 7. Vollansicht, Suche, Einstiege

`/hilfe` zeigt zwei Listen mit Titel und Beschreibungszeile: **Aufgaben**
nach `reihenfolge` kuratiert, **Referenz** alphabetisch. Der Unterschied
zwischen den Ebenen ist genau der — eine liest man der Reihe nach, die andere
schlägt man auf. Kein Kacheldesign, kein Willkommenstext, kein Suchfeld.

`/hilfe/{slug}` zeigt den Artikel in lesbarer Zeilenlänge, links die Liste
aller Artikel als Schiene — aus dem Index erzeugt, nicht pro Seite kopiert.

Auf `/hilfe` und `/hilfe/{slug}` gibt es **keine** Hilfespalte. Ein Artikel,
der erklärt, was der Leser gerade offensichtlich bedient, ist genau der Fehler
aus §8.1.

### 7.1 Suche in der ⌘K-Palette

Das `<Input>` in `apps/studio/resources/js/admin/components/CommandPalette.vue`
hat heute kein `v-model` und keinen Filter; der Doc-Kommentar sagt es offen
(„Search over the stock is not part of this"). Die Hilfe-Suche gibt diesem
Feld seine erste Funktion.

- Leeres Feld: heutiges Verhalten unverändert, „Zuletzt" mit besuchten
  Datensätzen.
- Tippen: zwei Gruppen — **Zuletzt** (nach Titel gefiltert, war bisher
  ungefiltert) und **Hilfe** (Titel und Volltext, mit Trefferauszug).

Der Index kommt über `GET /hilfe/suche`, einmal beim ersten Öffnen der
Palette, danach aus dem Modulzustand. Nicht als geteilte Inertia-Prop:
sechzig Kilobyte Index an *jeder* Antwort mitzuschicken, damit jemand
vielleicht ⌘K drückt, ist die teure Variante. Der Modulzustand überlebt
Inertia-Navigation, geholt wird also einmal je Seitenladung.

Ein Suchindex-Paket wäre Infrastruktur für ein Problem, das bei zweihundert
Artikeln anfinge; ein Substring-Filter über zweiundzwanzig reicht.

Was ein Treffer tut, hängt an derselben Schwelle wie der Knopf: darüber
klappt die Spalte auf und zeigt den Artikel, darunter wird nach
`/hilfe/{slug}` navigiert.

### 7.2 Einstiege

| Ort | Grund |
|---|---|
| Knopf im Arbeitsbereich | Kontext — der Artikel zu *diesem* Screen |
| Benutzermenü unten links, neben Profil und Abmelden | Global, auch von Screens ohne Artikel |
| Link auf `/einstellungen`, unterhalb der Panes | Der ausdrückliche Wunsch |

**Kein Eintrag in `SettingsNav`.** Die Schiene gruppiert Studio, Abrechnung,
Arbeit, Portal — Dinge, die man konfiguriert. „Hilfe" wäre das einzige
Element darin, das nichts einstellt.

## 8. Artikelbestand

### 8.1 Die Schreibregel

Die Spalte steht **neben** dem Screen. CLAUDE.mds Prosa-Regel gilt für Text,
der ohne Bedienoberfläche ankommt — E-Mail, PDF. Hier steht die Oberfläche
daneben, also greift der schärfere Test: *Sagt der Satz etwas, das der Leser
auf seinem eigenen Bildschirm nicht sieht?* Ein Artikel, der die Knöpfe
aufzählt, die rechts danebenstehen, wird gestrichen. Was bleibt: Reihenfolge,
Konsequenz und Vokabular, das nicht auf dem Schirm steht.

Das rettet auch den Referenzartikel zur Übersicht: nicht „hier siehst du deine
Zahlen", sondern wie eine Kennzahl gerechnet wird — ab wann eine Anfrage offen
zählt, was in die Quote eingeht.

**Anrede: du.** Wie die Mails, deren Register CLAUDE.md vorgibt. `de.json`
redet den Nutzer praktisch nie direkt an; für längere Prosa ist das keine
Option, und „Sie" klingt nach Software, die er gemietet hat.

### 8.2 Aufgaben — neun

| Slug | `screens` | Platzhalter |
|---|---|---|
| `erste-schritte` | `dashboard` | — |
| `anfrageformular-einbinden` | — | `%api_url%` — das Snippet aus `docs/inquiry-form.md`, kopierfertig |
| `lead-anlegen` | `leads.index`, `leads.show` | — |
| `projekttypen-und-felder` | `projects.index` | — |
| `angebot-erstellen` | `projects.quotes` | — |
| `galerie-veroeffentlichen` | `galleries.index` | — |
| `mail-weiterleitung` | `inbox.index` | `%inbound_address%` |
| `mcp-claude-verbinden` | — | `%mcp_url%` |
| `team-einladen` | `staff.index` | — |

`projekttypen-und-felder` hängt an `projects.index` und **nicht** an
`leads.index`, obwohl `ProjectSettings.vue` auf beiden Screens steht: auf
`leads.index` sitzt bereits `lead-anlegen`, und zwei Aufgaben je Route
verbietet die Regel aus §4.1. Das ist die Regel bei ihrer ersten Anwendung,
nicht eine Ausnahme von ihr.

Die Referenzartikel belegen jeweils den zweiten Platz derselben Route; wo eine
Aufgabe fehlt, stehen sie allein.

`team-einladen` beschreibt den Code, nicht die Mail: `StaffInvited` kündigt
einen 2FA-Schritt an, den `InvitationController` optional gemacht hat. Der
Widerspruch wird ein eigenes Issue, keine stille Mitreparatur.

### 8.3 Referenz — dreizehn

Je einer pro Navigationseintrag, Slug mit Suffix: `uebersicht-referenz`,
`anfragen-referenz`, `projekte-referenz`, `galerien-referenz`,
`kontakte-referenz`, `kalender-referenz`, `abwesenheiten-referenz`,
`benachrichtigungen-referenz`, `posteingang-referenz`, `pakete-referenz`,
`vorlagen-referenz`, `auswertungen-referenz`, `einstellungen-referenz`.

Kein `team-referenz`: `team-einladen` trägt das Rollenmodell schon, und
sonst ist auf `staff.index` nichts verborgen.

### 8.4 Phasen

Zweiundzwanzig Artikel sind der größere Teil der Schreibarbeit. Ein PR, aber
drei Etappen darin:

- **Phase 1** — Migration, Mechanik, dazu `erste-schritte`, `lead-anlegen`,
  `projekttypen-und-felder`, `mcp-claude-verbinden`, `mail-weiterleitung`.
  Damit ist alles beweisbar: Spalte, Umschalter, Palette, Platzhalter,
  Berechtigung. **Hier wird der Ton beurteilt, bevor siebzehn weitere Artikel
  daran hängen.**
- **Phase 2** — die restlichen vier Aufgabenartikel.
- **Phase 3** — die dreizehn Referenzartikel.

Ein Screen ohne Artikel zeigt keinen Knopf.

## 9. Tests

Pest im Container mit `-e APP_SURFACE=admin`, Vitest für TypeScript.

| Test | Sichert |
|---|---|
| Route-Name → Artikelliste über den Index, Aufgabe vor Referenz | Die kontextuelle Zuordnung |
| Artikel mit fehlender `faehigkeit` → 404, fehlt in `/hilfe/suche` | **Sicherheit** — der serverseitige Schnitt, für den dieser Ansatz gewählt wurde |
| Studioname `<script>` in `%inbound_address%` → escaped im HTML | **Sicherheit** — der einzige Weg, auf dem Nutzereingaben in gerendertes HTML gelangen |
| `%api_url%` gegen einen **gesetzten** `API_URL`-Wert, nicht gegen „nicht leer" | Der `APP_URL`-Fallback ergibt sonst einen falschen, aber plausiblen Host (§4.3) |
| Korpus-Wächter: jeder `screens:`-Eintrag ist ein existierender Route-Name | CLAUDE.mds „eine Route, die keiner geprüft hat", mechanisch statt per Regel |
| Korpus-Wächter: Frontmatter parst, `art` ∈ {aufgabe, referenz}, höchstens zwei Artikel je Route und einer je `art` | Ein kaputter Artikel bricht den Build, nicht die Seite |
| Vitest: `helpPanel.ts` — `ueberschrieben` fällt bei Navigation weg | Sonst klebt der Palettenartikel über allen Folgescreens |
| Vitest: `help.seen` wird nicht gesetzt, solange die Spalte unter der Schwelle unsichtbar bleibt | Sonst verfällt die Erstöffnung an einem Fenster, das sie nie zeigen konnte |

Die Korpus-Wächter sind der Kern: sie machen aus Regeln, an denen dieses
Repository schon gescheitert ist, einen fehlschlagenden Test.

**Keine E2E-Screenshots.** Baselines verrotten hier täglich; vierundfünfzig
rote Bilder wegen eines Panels sind teurer als der Nutzen.

## 10. Zwei Lücken, die keine Doku-Aufgabe sind

**Mehrere Postfächer.** Existiert nicht und ist per Design abgelehnt:
`docs/inbound-mail.md` hält fest, dass das Studio nie Postfach-Zugangsdaten
übergibt. Es gibt einen Inbound-Catch-All des Deployments und daraus
geminteter Adressen. Der zweite Adapter (`POST /api/mail/eingang`) ist
Deployment-Konfiguration an `MAIL_INBOUND_SIGNING_SECRET` und ohne gesetztes
Secret geschlossen — kein Inhaber-Feature. Ausführbar ist für den Inhaber
heute genau ein Weg, und den beschreibt `mail-weiterleitung`: Weiterleitung
fremder Adressen auf die eigene Studio-Adresse. Multi-Domain-Anbindung über
die API wird ein eigenes Issue.

**API-Token für n8n.** `App\Integration\Models\ApiToken` existiert, erzeugt
wird er aber nur von `App\Integration\Console\Commands\CreateApiTokenCommand`
— es gibt keinen Screen. Ein n8n-Artikel würde den Leser an eine
Container-Konsole verweisen. Ein Inhaber-Screen (Erzeugen, Scopes, Widerrufen,
einmalige Anzeige) wird ein eigenes Issue; der Artikel kommt mit dem Feature.

MCP/Claude ist davon nicht betroffen: die Verbindung läuft über RFC-7591
Dynamic Client Registration und den `/oauth/authorize`-Screen, der Inhaber
klickt zu und kopiert keinen Token.

## 11. Bekannte Risiken

**Die Migration, nicht die Hilfe.** 29 Templates werden angefasst. Der eigene
Commit davor (§3.3) ist die Gegenmaßnahme, `wide` auf allen 29 (§3.2) die
zweite: das Erscheinungsbild bleibt exakt, der Diff ist pro Datei drei Zeilen.

**Ein langlebiger Branch.** Ein PR für Migration, Mechanik und
zweiundzwanzig Artikel steht lange offen, während parallele Sitzungen an
denselben Dateien arbeiten. Gegenmaßnahme in der Reihenfolge: Migration und
Mechanik früh stabil, danach kommt nur noch Markdown dazu — und Markdown
kollidiert mit nichts.

**Container- statt Viewport-Queries.** Mit eingerasteter Spalte ist der
Arbeitsbereich schmaler als das Fenster, `SettingsNav` und andere brechen aber
auf Viewport-Queries um. Bestehende Eigenschaft, keine Regression dieses
Projekts, aber mit offener Spalte zuerst sichtbar. Vorgehen: Anfragen-Board,
`QuoteTable` und Auswertungen an der jeweiligen Schwelle von Hand ansehen,
Befund als `ponytail:`-Kommentar mit Obergrenze festhalten, nicht still
mitreparieren.
