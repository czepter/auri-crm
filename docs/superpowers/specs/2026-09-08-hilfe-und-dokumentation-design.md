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

**Drin:** eine Hilfedomäne unter `apps/studio/app/Help`; Artikel als Markdown
unter `apps/studio/resources/help`; serverseitiges Rendern mit
Platzhalterersetzung aus der laufenden Instanz, samt der zwei Umgebungswerte,
die der Admin-Prozess dafür noch nicht kennt (§3.3); eine auf- und
zuklappbare Spalte rechts im `AdminLayout`, die den zum aktuellen Screen
gehörenden Artikel zeigt; eine Vollansicht unter `/hilfe`; Hilfe-Suche in der
bestehenden ⌘K-Palette; dreiundzwanzig Artikel in zwei Ebenen, in drei Phasen
ausgeliefert.

**Draußen, benannt damit die Auslassungen Entscheidungen sind:** eine
öffentliche Doku-Domain (`docs.auri-crm.de` — die Doku liegt hinter Auth, und
eine zweite Astro-Anwendung kostet ein siebtes Image in
`release-image.yml`, einen Service in `docker-compose.coolify.yml`, eine
Domain und ein Sentry-DSN, ohne dass irgendetwas davon einem eingeloggten
Inhaber nützt); Artikel, die zur Laufzeit editierbar sind (ein zweites CMS für
ein Ein-Studio-Produkt; Inhalte gehören zum Release); Screenshots in Artikeln
(sie verrotten schneller als die Doku gepflegt wird — dieselbe Erfahrung wie
mit den E2E-Baselines); ein Artikel über die Anbindung mehrerer Postfächer
(das Feature existiert nicht, siehe §9); ein Artikel über API-Token für n8n
(der Token ist heute nur per `php artisan` erzeugbar, siehe §9);
Pfeiltasten-Navigation in der ⌘K-Palette (die Liste ist heute reine
`<a>`-Elemente, ein Roving-Tabindex ist eigene Arbeit und kein Anhängsel);
eine zweite Sprache (`de.json` ist einsprachig, die Artikel sind es auch).

## 3. Ablage und Inhaltsmodell

Flach unter `apps/studio/resources/help/`, Dateiname ist der Slug:

```
apps/studio/resources/help/
  lead-anlegen.md
  projekttypen-und-felder.md
  mcp-claude-verbinden.md
  referenz-anfragen.md
  …
```

Flach, weil der Slug ohnehin eindeutig sein muss und `/hilfe/lead-anlegen`
dann keine Pfadlogik braucht. Die Ebene steht im Frontmatter, nicht im
Verzeichnis.

`apps/studio/resources/` und nicht `docs/`, weil nur `apps/studio` im
Produktionsimage landet. Ein Hilfesystem, das lokal Artikel hat und in
Produktion leer ist, fällt erst beim Kunden auf.

### 3.1 Frontmatter

`symfony/yaml` liegt bereits in `apps/studio/composer.lock`.

```yaml
---
titel: Lead anlegen
beschreibung: Eine Anfrage von Hand erfassen und durch die Stufen führen.
art: aufgabe                      # aufgabe | referenz
screens: [leads.index, leads.show]
faehigkeit: manage_leads          # optional
---
```

`screens` sind **Route-Namen**. Damit ist die Zuordnung Screen → Artikel keine
zweite Tabelle an einer dritten Stelle, sondern steht im Artikel selbst; die
Rückwärtsabbildung wird daraus erzeugt. `SettingsNav.vue` trägt den Kommentar,
warum das wichtig ist: als jede Seite ihre Liste selbst hielt, zeigten fünf
von acht Seiten Panes als „bald", die längst ausgeliefert waren.

`faehigkeit` ist ein Wert des bestehenden `Capability`-Enums, keine neue
Rollenerfindung. Fehlt der Schlüssel, sieht den Artikel jeder, der den Screen
erreicht.

### 3.2 Platzhalter

| Platzhalter | Quelle |
|---|---|
| `%api_url%` | `config("services.api.url")` — neu, siehe §3.3 |
| `%inbound_address%` | `StudioSettings` — die gemintete Catch-All-Adresse |
| `%mcp_url%` | `config("services.mcp.url")` — neu, siehe §3.3 |
| `%studio_name%` | `StudioSettings` |

Das Trennzeichen ist `%…%` und nicht `{{ … }}`, weil n8n-Ausdrücke wörtlich
`{{ $json.feld }}` sind. Der n8n-Artikel ist aufgeschoben (§9), aber das
Trennzeichen später zu wechseln kostet eine Migration über den ganzen Korpus.

**Sicherheitsrelevant:** Der Artikeltext stammt aus dem Repository und wird
als HTML gerendert. Die **Platzhalterwerte** stammen teilweise aus Eingaben
des Inhabers — der Studioname steckt geslugged in der Inbound-Adresse. Sie
werden beim Einsetzen escaped. Die Ersetzung passiert **nach** dem
Markdown-Rendern, auf dem fertigen HTML, sonst kann ein Wert die Struktur
brechen.

### 3.3 Zwei Werte, die der Admin-Prozess heute nicht kennt

`docker-compose.coolify.yml` gibt dem `admin`-Service `PORTAL_URL` und
`GALLERY_URL`, aber weder eine API- noch eine MCP-Domain. `config/app.php`
kennt nur `APP_URL`, und das ist auf diesem Container die Studio-Domain. Ohne
Nachrüstung liefern `%api_url%` und `%mcp_url%` in Produktion nichts — und
das trifft ausgerechnet `anfrageformular-einbinden` und
`mcp-claude-verbinden`, die beiden Artikel, wegen derer die Platzhalter
überhaupt existieren.

Nachzurüsten ist:

- `API_URL` und `MCP_URL` im `admin`-Service in `docker-compose.coolify.yml`,
  aus `SERVICE_FQDN_API` und `SERVICE_FQDN_MCP` — beide Domains sind laut
  `docs/deployment.md` ohnehin zu vergeben. `docker/app/check-coolify-compose.php`
  prüft diese Datei, die Ergänzung muss also durch dessen Regeln.
- Zwei Einträge in `config/services.php`, neben dem bestehenden `gallery`,
  jeweils mit `APP_URL`-Fallback für den Einzelhost-Betrieb, den die
  Testsuite fährt. Dieselbe Form, die `config/portal.php` mit eigener
  Begründung vorführt.
- Beide Schlüssel in `apps/studio/.env.example`.

Ein fehlender Wert fällt auf `APP_URL` zurück und ergibt einen falschen, aber
plausiblen Host. Deshalb prüft ein Test die Ersetzung gegen einen gesetzten
Wert und nicht gegen „nicht leer" (§8).

### 3.4 Cache

Geteilt, mit unterschiedlicher Begründung je Hälfte:

- **Index** (Slug, Titel, Beschreibung, Art, Screens, Fähigkeit, Reintext für
  die Suche): gecacht. Ohne ihn liest jeder Seitenaufruf dreiundzwanzig
  Dateien, nur um zu wissen, ob es für diesen Screen einen Artikel gibt.
- **Artikeltext**: nicht gecacht. Eine Datei parsen ist etwa eine
  Millisekunde, ein Cache dafür ist Infrastruktur ohne Anlass.

Der Cache-Schlüssel enthält den Hash über die `filemtime` aller Hilfedateien.
`docker/app/entrypoint.sh` räumt bewusst keine Caches (siehe dort den
`ponytail:`-Kommentar zu `config:cache`), und der Store ist Redis — der
überlebt den Deploy. Ohne selbstinvalidierenden Schlüssel stünde nach einem
Release der Index des vorigen. Dreiundzwanzig `stat`-Aufrufe sind ein
Bruchteil von dreiundzwanzig Dateilesungen samt YAML, der Cache lohnt sich
also weiterhin und hängt an keinem Deploy-Schritt.

## 4. Serverseite

Neue Domäne `apps/studio/app/Help`, gebaut wie ihre Geschwister:

```
app/Help/
  Actions/RenderHelpArticle.php     Datei lesen, Frontmatter trennen, Str::markdown, Platzhalter einsetzen
  Data/HelpArticle.php              slug, titel, art, screens, faehigkeit, html
  Data/HelpEntry.php                Index-Eintrag ohne html
  Support/HelpIndex.php             einlesen, cachen, Route-Name → Slug auflösen, nach Fähigkeit filtern
  Support/HelpPlaceholders.php      die vier Werte, einmal, escaped
  Http/Controllers/HelpController.php
```

`league/commonmark` liegt bereits im Lock (Laravel zieht es für
Mail-Markdown), `Str::markdown()` funktioniert ohne neue Abhängigkeit.

### 4.1 Routen

In `apps/studio/routes/admin.php`, in der Staff-Auth-Gruppe:

| Route | Antwort | Zweck |
|---|---|---|
| `GET /hilfe` | Inertia | Vollansicht, beide Ebenen |
| `GET /hilfe/{slug}` | Inertia | Vollansicht eines Artikels |
| `GET /hilfe/artikel/{slug}` | JSON | Was die Spalte beim Aufklappen holt |
| `GET /hilfe/suche` | JSON | Index für die ⌘K-Palette, einmal pro Seitenladung |

### 4.2 Warum die Spalte nicht über Inertia lädt

Ein `router.reload({ only: ['helpArticle'] })` würde die komplette
Controller-Aktion des aktuellen Screens erneut ausführen — sämtliche Queries
des Projektscreens, um einen Hilfetext zu holen. Inertia wertet zwar nur die
angeforderte Prop aus, der Controller-Rumpf läuft trotzdem.

Die Spalte holt deshalb `GET /hilfe/artikel/{slug}` per `fetch` und legt das
Ergebnis in eine Map im Modulzustand. Zweites Aufklappen ist ohne Netz.

### 4.3 Was auf jedem Request geteilt wird

Genau eine Zeile in `HandleInertiaRequests::share()`:

```
'help' => ['slug' => …, 'titel' => …]   // oder null
```

Aufgelöst aus dem gecachten Index über `$request->route()->getName()`,
gefiltert über `faehigkeit`. Kein Dateizugriff und kein Markdown-Parsing,
solange die Spalte zu ist. `null` heißt: für diesen Screen gibt es keine
Hilfe, der Knopf erscheint nicht.

### 4.4 Berechtigung

Ein Artikel, dessen `faehigkeit` der Nutzer nicht hat, taucht weder im Index
auf noch in `/hilfe` noch in `/hilfe/suche`, und `/hilfe/{slug}` antwortet 404
statt 403. Das ist dieselbe Regel, die `AdminLayout` auf seine Navigation
anwendet: ein Eintrag, hinter dem für diesen Nutzer nichts steht, existiert
für ihn nicht.

Kein Artikel für einen Screen ist der Normalfall und kein Fehler.

## 5. Die Spalte

Dritte Flex-Spalte in `apps/studio/resources/js/admin/layouts/AdminLayout.vue`,
direkt nach `<main>`. Das Layout ist heute `nav (flex-none)` plus
`main (flex-1 min-w-0)`; `main` schrumpft dank `min-w-0` bereits korrekt.

```
[ nav 214px ] [ main flex-1 ] [ aside 340px ]
```

**Kein Overlay, kein Scrim, kein Fokusfang, kein `aria-modal`.** `<aside>` ist
ein `complementary`-Landmark, und Escape schließt nicht — das Panel ist kein
Dialog, und Escape gehört dem Screen, an dem weitergearbeitet wird. Der ganze
Sinn der Spalte ist, dass die Arbeit stehenbleibt.

`sticky top-0 h-screen overflow-y-auto`, wie die Navigation links: der Artikel
scrollt für sich, während der Arbeitsbereich scrollt.

### 5.1 Zustand

`localStorage`, Schlüssel `help.open`. `apps/studio/resources/js/admin/main.ts`
vergibt keine persistenten Inertia-Layouts — jede Seite bringt `<AdminLayout>`
selbst mit, das Layout mountet bei jeder Navigation neu. Der Zustand wird
deshalb synchron im `setup()` gelesen, nicht in `onMounted`, sonst blitzt das
Panel bei jedem Seitenwechsel auf.

Nach der Navigation wechselt der Slug ohnehin. Das Panel holt den neuen
Artikel und zeigt bis dahin den Titel aus der geteilten `help`-Prop — kein
Ladeblitz auf leerem Grund.

Geteilter Zustand in `apps/studio/resources/js/admin/lib/helpPanel.ts`,
Modulebene, drei Verbraucher (Knopf, Spalte, Palette):

```
{ offen: boolean, ueberschrieben: string | null }
```

Angezeigter Slug ist `ueberschrieben ?? page.props.help.slug`. Ein
Navigationswechsel setzt `ueberschrieben` zurück, sonst klebt der in der
Palette gewählte Artikel über allen folgenden Screens.

### 5.2 Schwelle

Die Navigation klappt bei ≤1080px auf Icons zusammen. Darunter würden 340px
den Arbeitsbereich zerdrücken — das Anfragen-Board hat drei Spalten,
`QuoteTable` und die Auswertungen sind breit.

| Viewport | Verhalten |
|---|---|
| > 1080px | Spalte rastet ein, Toggle klappt auf und zu |
| ≤ 1080px | Kein Panel; der Knopf wird ein `<Link>` nach `/hilfe/{slug}` |

`useMediaQuery` aus `@vueuse/core`, liegt bereits in `apps/studio/package.json`.

### 5.3 Prosa-Styles

Das Projekt hat kein `@tailwindcss/typography`. Das Artikel-HTML bekommt etwa
zwanzig Zeilen eigene Regeln für `h2 h3 p ul ol code pre a` im bestehenden
Token-System. Eine neue Abhängigkeit für sieben Elementtypen wäre der falsche
Tausch.

### 5.4 Der Knopf

Oben in der Spalte des Arbeitsbereichs, nicht in der linken Navigation — er
wechselt keine Seite, er öffnet ein Werkzeug. Dieselbe Begründung, mit der die
Suche über der Navigation steht und nicht darin. Er erscheint nur, wenn `help`
nicht `null` ist.

## 6. Vollansicht, Suche, Einstiege

`/hilfe` zeigt zwei Listen, `Aufgaben` und `Referenz`, mit Titel und der
Beschreibungszeile aus dem Frontmatter. Kein Kacheldesign, kein
Willkommenstext, **kein eigenes Suchfeld**.

`/hilfe/{slug}` zeigt den Artikel in lesbarer Zeilenlänge, links die Liste
aller Artikel als Schiene — aus dem Index erzeugt, nicht pro Seite kopiert.

### 6.1 Suche in der ⌘K-Palette

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
Inertia-Navigation, geholt wird also einmal pro Seitenladung.

Ein Suchindex-Paket wäre Infrastruktur für ein Problem, das bei zweihundert
Artikeln anfinge; ein Substring-Filter über dreiundzwanzig reicht.

Was ein Treffer tut, hängt an derselben Schwelle wie der Knopf: über 1080px
klappt die Spalte auf und zeigt den Artikel, darunter wird nach
`/hilfe/{slug}` navigiert.

### 6.2 Einstiege

| Ort | Grund |
|---|---|
| Knopf im Arbeitsbereich | Kontext — der Artikel zu *diesem* Screen |
| Benutzermenü unten links, neben Profil und Abmelden | Global, auch von Screens ohne Artikel |
| Link auf `/einstellungen`, unterhalb der Panes | Der ausdrückliche Wunsch |

**Kein Eintrag in `SettingsNav`.** Die Schiene gruppiert Studio, Abrechnung,
Arbeit, Portal — Dinge, die man konfiguriert. „Hilfe" wäre das einzige
Element darin, das nichts einstellt.

## 7. Artikelbestand

### 7.1 Die Schreibregel

Die Spalte steht **neben** dem Screen. CLAUDE.mds Prosa-Regel gilt für Text,
der ohne Bedienoberfläche ankommt — E-Mail, PDF. Hier steht die Oberfläche
daneben, also greift der schärfere Test: *Sagt der Satz etwas, das der Leser
auf seinem eigenen Bildschirm nicht sieht?* Ein Artikel, der die Knöpfe
aufzählt, die rechts danebenstehen, wird gestrichen. Was bleibt: Reihenfolge,
Konsequenz und Vokabular, das nicht auf dem Schirm steht.

Das rettet auch den Referenzartikel zur Übersicht: nicht „hier siehst du deine
Zahlen", sondern wie eine Kennzahl gerechnet wird — ab wann eine Anfrage offen
zählt, was in die Quote eingeht.

### 7.2 Aufgaben — neun

| Slug | Platzhalter |
|---|---|
| `erste-schritte` | — |
| `anfrageformular-einbinden` | `%api_url%` — das Snippet aus `docs/inquiry-form.md`, kopierfertig |
| `lead-anlegen` | — |
| `projekttypen-und-felder` | — |
| `angebot-erstellen` | — |
| `galerie-veroeffentlichen` | — |
| `mail-weiterleitung` | `%inbound_address%` |
| `mcp-claude-verbinden` | `%mcp_url%` |
| `team-einladen` | — |

`team-einladen` beschreibt den Code, nicht die Mail: `StaffInvited` kündigt
einen 2FA-Schritt an, den `InvitationController` optional gemacht hat. Der
Widerspruch wird ein eigenes Issue, keine stille Mitreparatur.

### 7.3 Referenz — vierzehn

Je einer pro Navigationseintrag: `uebersicht`, `anfragen`, `projekte`,
`galerien`, `kontakte`, `kalender`, `abwesenheiten`, `benachrichtigungen`,
`posteingang`, `pakete`, `vorlagen`, `team`, `auswertungen`, `einstellungen`.

### 7.4 Phasen

Dreiundzwanzig Artikel sind der größere Teil der Arbeit, nicht die Mechanik.

- **Phase 1** — Mechanik vollständig, dazu `erste-schritte`, `lead-anlegen`,
  `projekttypen-und-felder`, `mcp-claude-verbinden`, `mail-weiterleitung`.
  Damit ist alles beweisbar: Spalte, Palette, Platzhalter, Berechtigung.
- **Phase 2** — die restlichen vier Aufgabenartikel.
- **Phase 3** — die vierzehn Referenzartikel.

Ein Screen ohne Artikel zeigt keinen Knopf. Phase 1 ist damit auslieferbar und
nicht halb.

## 8. Tests

Pest im Container mit `-e APP_SURFACE=admin`, Vitest für TypeScript.

| Test | Sichert |
|---|---|
| Route-Name → Slug über den Index | Die kontextuelle Zuordnung |
| Artikel mit fehlender `faehigkeit` → 404, fehlt in `/hilfe/suche` | **Sicherheit** — der serverseitige Schnitt, für den dieser Ansatz gewählt wurde |
| Studioname `<script>` in `%inbound_address%` → escaped im HTML | **Sicherheit** — der einzige Weg, auf dem Nutzereingaben in gerendertes HTML gelangen |
| `%api_url%` gegen einen **gesetzten** `API_URL`-Wert, nicht gegen „nicht leer" | Der `APP_URL`-Fallback ergibt sonst einen falschen, aber plausiblen Host (§3.3) |
| Korpus-Wächter: jeder `screens:`-Eintrag ist ein existierender Route-Name | CLAUDE.mds „eine Route, die keiner geprüft hat", mechanisch statt per Regel |
| Korpus-Wächter: Frontmatter parst, `art` ∈ {aufgabe, referenz} | Ein kaputter Artikel bricht den Build, nicht die Seite |
| Vitest: `helpPanel.ts` — `ueberschrieben` fällt bei Navigation weg | Sonst klebt der Palettenartikel über allen Folgescreens |

Die beiden Korpus-Wächter sind der Kern: sie machen aus zwei Regeln, an denen
dieses Repository schon einmal gescheitert ist, einen fehlschlagenden Test.

**Keine E2E-Screenshots.** Baselines verrotten hier täglich;
vierundfünfzig rote Bilder wegen eines Panels sind teurer als der Nutzen.

## 9. Zwei Lücken, die keine Doku-Aufgabe sind

**Mehrere Postfächer.** Existiert nicht und ist per Design abgelehnt:
`docs/inbound-mail.md` hält fest, dass das Studio nie eine Postfach-Zugangsdaten
übergibt. Es gibt einen Inbound-Catch-All des Deployments und daraus geminteter
Adressen. Der zweite Adapter (`POST /api/mail/eingang`) ist
Deployment-Konfiguration an `MAIL_INBOUND_SIGNING_SECRET` und ohne gesetztes
Secret geschlossen — kein Inhaber-Feature. Ausführbar ist für den Inhaber heute
genau ein Weg, und den beschreibt `mail-weiterleitung`: Weiterleitung fremder
Adressen auf die eigene Studio-Adresse.

Multi-Domain-Anbindung über die API wird ein eigenes Issue.

**API-Token für n8n.** `App\Integration\Models\ApiToken` existiert, erzeugt
wird er aber nur von `App\Integration\Console\Commands\CreateApiTokenCommand`
— es gibt keinen Screen. Ein n8n-Artikel würde den Leser an eine
Container-Konsole verweisen. Ein Inhaber-Screen (Erzeugen, Scopes, Widerrufen,
einmalige Anzeige) wird ein eigenes Issue; der Artikel kommt mit dem Feature.

MCP/Claude ist davon nicht betroffen: die Verbindung läuft über RFC-7591
Dynamic Client Registration und den `/oauth/authorize`-Screen, der Inhaber
klickt zu und kopiert keinen Token.

## 10. Bekanntes Risiko

Bei 1280px Fensterbreite bleiben mit eingerasteter Spalte etwa 690px
Arbeitsbereich. `SettingsNav` und andere brechen auf *Viewport*-Queries um,
die dann nicht feuern — der Container ist schmal, das Fenster nicht. Das ist
eine bestehende Eigenschaft und keine Regression dieses Projekts, wird aber
mit offener Spalte zuerst sichtbar.

Vorgehen: Anfragen-Board, `QuoteTable` und Auswertungen bei 1280px mit offener
Spalte von Hand ansehen, den Befund als `ponytail:`-Kommentar mit Obergrenze
festhalten, nicht still mitreparieren.
