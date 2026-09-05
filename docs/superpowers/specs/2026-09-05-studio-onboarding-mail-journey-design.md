# Willkommens- und Onboarding-Mails für neue Studios

Status: Entwurf, im Gespräch abgestimmt. Texte am Canvas abgenommen.
Datum: 2026-09-05

## Ziel

Ein frisch angelegtes Studio bekommt heute keine einzige Mail, und seine
Adresse wird nie geprüft. Diese Spec schließt beides: vier Mails an den
Inhaber, eine E-Mail-Bestätigung dahinter, und ein gemeinsames Layout aus
MJML für alles Ausgehende.

| Mail | Absender | Auslöser |
|---|---|---|
| Bestätige deine E-Mail-Adresse | Produkt | Account-Anlage, vor der Willkommensmail |
| Willkommen bei Auri CRM | Produkt | Account-Anlage (`SetupController::store()`) |
| Noch ein paar Einstellungen | Produkt | tägliches Kommando, zwei Tage nach Anlage, Assistent unfertig |
| Deine Adressen bei Auri CRM | Produkt | Abschluss des Assistenten (`StudioOnboardingController::complete()`) |

Nicht im Umfang: Drip-Kampagne über Wochen, Journey-Tabelle mit
Schritt-Status, Mails an Kunden.

## Ausgangslage

- Alle Mails sind heute `MailMessage` im Laravel-Default-Theme.
  `resources/views/vendor/mail` existiert nicht.
- Es gibt keine E-Mail-Bestätigung für Staff. `User` implementiert kein
  `MustVerifyEmail`, keine Migration legt `email_verified_at` an, es gibt
  weder Route noch Middleware. `SetupController::store()` meldet den Inhaber
  sofort an. Für den *Wechsel* einer Adresse existiert dagegen ein fertiges
  Muster: `UserEmailChange` mit `token_hash`, `expires_at`, `consumed_at`,
  `scopeLive()` und uniformem Scheitern.
- `AppliesStudioMailIdentity` setzt From, Reply-To, BCC und hängt Signatur
  und Rechtsfußzeile als Textzeilen an. Gilt für Mails **des Studios**; die
  drei Mails hier verwenden den Trait nicht.
- `NotificationService` ist der einzige Fan-out-Punkt. Ruhezeiten und
  Präferenzen werden ausschließlich dort geprüft; `Transactional` umgeht
  beides.
- Das Laufzeit-Image hat kein Node. `docker/app/Dockerfile` zieht
  `node:24-alpine` nur für die `assets`-Stufe; die finale `app`-Stufe ist
  reines FrankenPHP.
- `/branding/logo` (`routes/portal.php:207`) ist öffentlich erreichbar.
- Routen sind flächengebunden. Ein Prozess mit `APP_SURFACE=admin` kennt die
  Portal-Routen nicht, deshalb `config('portal.url')` statt `route()`, so
  wie `NotifyZipWaiters:25` es bereits macht.
- `InboundAddress::catchAll($displayName, $secret)` prägt die Anfrage-Adresse
  aus `studio_settings.inbound_secret`. `InboxController:74` baut sie schon
  so; Mail 3 ruft dieselbe Stelle.
- `studio_settings` wird von der Migration befüllt. `created_at` ist der
  Installationszeitpunkt; Anker für "vor zwei Tagen" ist `users.created_at`
  des frühesten Inhabers.
- CI hat mit `packages/contracts/openapi.json` bereits einen
  Generieren-und-Diffen-Check (`ci.yml:213`). Die MJML-Ausgabe bekommt
  denselben.
- Die Spec vom 2026-09-04 (Mandantenfähigkeit) verschiebt den Auslöser der
  Willkommensmail später von `SetupController` zur Selbstregistrierung. Die
  Notification-Klassen überleben den Umzug, der Aufrufort nicht.

## Entscheidungen

| Frage | Entscheidung | Verworfen |
|---|---|---|
| MJML ausführen | Build-Zeit, Ergebnis eingecheckt, CI-Drift-Check | `spatie/mjml-php` zur Laufzeit (Node ins Laufzeit-Image, Subprozess je Versand für Ausgabe, die nie variiert); nur in der `assets`-Stufe kompilieren (Tests und lokale Entwicklung stünden ohne Views da) |
| Reichweite | `layout` und `button` überschreiben, alles andere fällt auf Laravels Komponenten zurück | Den kompletten Satz veröffentlichen (zehn Dateien Diff ohne Anlass); `panel` mitgenerieren, das `MailMessage` nicht aufrufen kann |
| Absender | zwei Marken: Produkt für diese drei, Studio für die übrigen vierzehn | eine Marke für alles: eine Willkommensmail unter dem Namen des Studios begrüßt niemanden, der dieses Studio selbst gerade angelegt hat |
| Platzhalter | Sentinel-Token (`__SLOT__`), nach `mjml` zu Blade ersetzt | `{{ }}` direkt in die MJML-Quelle, ungeklärtes Escaping in Attributpositionen |
| Logo | absolute URL auf `config('portal.url')."/branding/logo"` | CID-Embed; `MailMessage` reicht kein `$message` in die Markdown-Views durch |
| Akzentfarbe | inline aus der MJML-Ausgabe | `themes/default.css`, statisch, trägt keinen Laufzeitwert je Mandant |
| Erinnerung | tägliches Kommando plus `onboarding_reminder_sent_at` | verzögerter Job, läge zwei Tage in Redis |
| Präferenzen | nicht in `StudioEvents::ALL` | eine Checkbox "Willkommensmail" auf einer Pane, die man erst nach Erhalt sieht |
| Mail-Texte | fest im PHP, wie `StaffInvited` | `de.json`, dort gehen Notification-Texte nicht durch |
| Bestätigung einlösen | Code **und** Link, beide auf denselben Datensatz | nur Link (langsamer, obwohl der Inhaber ohnehin am Schirm sitzt); nur Code (unbrauchbar, wenn die Mail auf dem Telefon geöffnet wird) |
| Aufbau der Willkommensmail | Intro mit CTA, drei Funktionsblöcke mit je eigenem CTA, Grußformel | eine Mail, eine Handlung: zeigt dem Inhaber nichts, was er noch nicht gefunden hat |
| Grußformel | Produktmails "Viele Grüße / Auri CRM", Studiomails keine | "dein Auri-CRM-Team" erfindet ein Team, das ein Ein-Personen-OSS-Projekt nicht hat; der Name des Inhabers behauptet eine Handschrift bei einer systemerzeugten Mail und konkurriert mit `mailSignature()` |
| Fuß | Ziel-Link zu den GitHub-Issues, Grund des Versands | Impressum: Auri CRM ist in dieser Phase Open Source, kein gewerblicher Absender. Kommt zurück, sobald daraus ein Angebot wird |

## 1 Pipeline

Quellen in `apps/studio/resources/mail/`, Ausgabe eingecheckt:

```
resources/mail/layout.mjml  →  resources/views/vendor/mail/html/layout.blade.php
resources/mail/button.mjml  →  resources/views/vendor/mail/html/button.blade.php
```

`mjml` als devDependency, Skript `mail:build` ruft `scripts/build-mail.mjs`.
Das Skript kompiliert und ersetzt danach die Sentinel: `__SLOT__`,
`__HEADER__`, `__FOOTER__`, `__SUBCOPY__` zu `{{ $slot }}` usw.,
`__BRAND__`, `__ACCENT__`, `__LOGO_URL__`, `__URL__` entsprechend.

`{{ }}` genügt auch für `$slot`: Laravels `e()` lässt `Htmlable` unangetastet.

CI-Schritt im `web`-Job:

```
pnpm --filter @studio/app mail:build
git diff --exit-code -- apps/studio/resources/views/vendor/mail
```

**Zuerst zu beweisen:** dass Laravel eine teilweise veröffentlichte
Komponentenmenge akzeptiert und für `message`, `header`, `footer` weiter auf
die eigenen Views zurückfällt. Ein Render-Test gegen `StaffInvited` zeigt das.

## 2 Zwei Absender in einem Layout

Das Layout ist dasselbe, Kopf und Fuß unterscheiden sich:

| | Produkt | Studio |
|---|---|---|
| Wortmarke | Auri CRM | `display_name`, sonst Logo aus `logoPath()` |
| Absenderadresse | `MAIL_FROM_ADDRESS` | `StudioSettingsResolver::mailFromAddress()` |
| Fuß | Produktzeile plus Grund des Versands | Signatur und Rechtszeile |
| Trait | keiner | `AppliesStudioMailIdentity` |

Für die Studio-Seite genügt ein `View::composer` auf `mail::html.layout`, der
Marke, Akzent und Logo-URL aus dem Resolver einspeist; die vierzehn
bestehenden Notifications ändern sich dann nicht.

**Zuerst zu beweisen:** wie das Layout erfährt, welcher der beiden Fälle
gerade rendert. Ein View-Composer sieht die Notification nicht. Empfehlung:
die drei Produkt-Notifications rendern über eine eigene Markdown-View, die
dasselbe kompilierte Layout mit gesetzter Produktmarke einbindet, statt den
Composer um einen Zustandsschalter zu erweitern. Vor der Umsetzung an einer
echten Notification prüfen.

## 3 Die drei Mails

`Notification` plus `MailMessage` unter `app/Notifications/Studio/`, Versand
über `NotificationService`. Keine ist `Transactional`: niemand wartet auf
sie, und eine Willkommensmail um 03:00 ist der Fall, für den es Ruhezeiten
gibt.

Ton: nudge and nurture. Freundlich stupsen, ohne Druck, und ohne die
Lehrerhaltung aus Anweisung, Begründung, Folge und Belohnung. Wärme ist
Begrüßung oder guter Wunsch, nie eine Antwort auf eine ungestellte Sorge. Jeder Satz muss
außerdem etwas tragen, das der Leser auf seinem Bildschirm nicht sieht
(CLAUDE.md, "Prose copy that arrives without a UI"). Deshalb erklärt keine
der drei Mails eine Oberfläche.

**StudioEmailVerification** — `SetupController::store()`, vor allem anderen

```
Betreff: Bestätige deine E-Mail-Adresse

Hallo {name},

bitte bestätige die E-Mail-Adresse deines Auri-CRM-Kontos mit diesem Code:

    140736

Der Code gilt 30 Minuten.

[E-Mail-Adresse bestätigen]  →  /e-mail-bestaetigen/{token}

Oder diesen Link öffnen:
https://…/e-mail-bestaetigen/{token}
```

`Transactional`: der Inhaber wartet gerade darauf, Ruhezeiten halten sie
nicht auf. Die rohe URL steht ausgeschrieben da, weil ein zweiter Link an
derselben Stelle nicht hilft, wenn der Button nicht funktioniert.

**StudioWelcome** — `SetupController::store()`

```
Betreff: Willkommen bei Auri CRM

Hallo {name},

willkommen bei Auri CRM. Ab jetzt liegt alles zu deinen Aufträgen an einem
Ort, von der ersten Anfrage bis zu den fertigen Bildern. Drei Funktionen,
die du vielleicht noch nicht kennst:

[Studio öffnen]  →  /

── Ein eigener Bereich für deine Kunden
Angebot, Vertrag und Rechnung liegen im Kundenportal bereit. Deine Kunden
melden sich **ohne Passwort** an, unterschreiben den Vertrag im Browser und
zahlen per Überweisung oder Karte.
[Portal einrichten]

── Galerien, die Gäste ohne Konto sehen
Fertige Bilder gehen als Galerie raus statt als Link mit Ablaufdatum. Gäste
öffnen sie **ohne Konto**, markieren Favoriten und laden ihre Auswahl als
ZIP herunter.
[Galerie anlegen]

── Anfragen und Antworten in einem Posteingang
Dein Studio hat eine **eigene Mailadresse für Anfragen**. Was dort ankommt,
landet im Posteingang. Antworten deiner Kunden hängen sich von selbst an den
richtigen Auftrag.
[Posteingang ansehen]

Viele Grüße
dein Auri-CRM-Team
```

Erwähnt die Einrichtung nicht. Die Mail geht bei der Anlage raus, der Inhaber
landet sofort im Assistenten und ist oft durch, bevor er sie öffnet. `/`
stimmt in beiden Fällen, weil `RequireStudioOnboarding` jeden GET auf den
Assistenten umleitet, solange der offen ist.

Jeder Behauptungssatz ist im Code geprüft: Magic-Link-Anmeldung,
`ContractSigned`, Überweisung/Karte aus `de.json`, `GalleryGuestCode`,
`/favorit`, `GalleryZipReady`, `InboundAddress` und die Thread-Zuordnung über
die HMAC-Reply-Adresse.

**StudioSetupPending** — tägliches Kommando, siehe Abschnitt 4

```
Betreff: Noch ein paar Einstellungen

Hallo {name},

um mit Auri CRM richtig durchzustarten, fehlen dir noch ein paar
Einstellungen.

[Weiter einrichten]  →  /einrichtung/studio
```

Eine Erinnerung ist ein Stups. Eine Vorfassung nannte den ersten Schritt, den
Grund dafür, die Folge des Aufschiebens und eine erfundene Dauer als Anreiz:
eine Lektion mit Belohnung dran. Zwei weitere beruhigten: "Was du schon eingetragen
hast, ist gespeichert" und "lass dir Zeit, dein Studio läuft dir nicht weg".
Beide antworten auf Sorgen, die niemand geäußert hat, und sähen sie damit
erst. Diese Mail trägt keinen Schlusssatz; ihre Freundlichkeit steckt in
"richtig durchzustarten".

**StudioReady** — `StudioOnboardingController::complete()`

```
Betreff: Deine Adressen bei Auri CRM

Hallo {name},

die Einrichtung steht. Dein Studio hat dabei zwei eigene Adressen bekommen.

[Erste Anfrage anlegen]  →  /anfragen/neu

── Anfragen per Mail
{InboundAddress::catchAll(displayName, inboundSecret)}
Die kannst du auf deiner Website verlinken.
[Posteingang öffnen]  →  /posteingang

── Dein Kundenportal
{config('portal.url')}
Gebuchte Kunden bekommen ihren Zugang per Mail.
[Portal ansehen]

Viele Grüße
Auri CRM
```

Der Betreff ist aufs Wiederfinden geschrieben. Beim Portal steht bewusst
"bekommen ihren Zugang per Mail" und nicht "den Link bekommen sie von dir":
`ClientAccessLink` verschickt den Magic-Link, nicht der Inhaber von Hand.

Was die Rotation mit alten Adressen macht, gehört in die Doku, nicht hierher.
Ein Sonderfall zu einer Funktion, die der Leser noch nie benutzt hat, wirkt
nur deshalb wertvoll, weil die Mail sonst leer aussieht.

**Zu entscheiden:** Die Adresse enthält das Secret. `docs/inbound-mail.md`
sieht genau diese Adresse für die öffentliche Studio-Website vor, der
Mailversand gibt also nichts Neues preis. Wer das anders bewertet, nennt in
der Mail nur `/posteingang` als Fundort.

**StaffInvited** — die eine Bestandsmail, die mit überarbeitet wurde

```
Betreff: Dein Zugang zu {studio}

Hallo {name},

{invitedBy} hat dich als {role} eingeladen. Schön, dass du dabei bist.

[Zugang einrichten]

Der Link gilt 7 Tage.

── Deine Termine im eigenen Kalender
Die Shootings, für die du eingeteilt bist, kannst du in deiner Kalender-App
abonnieren.
[Kalender abonnieren]  →  /einstellungen/kalender

── Antworten am Auftrag
Kundenantworten landen am Auftrag, auch wenn der Kunde aus seinem eigenen
Mailprogramm schreibt.
[Zum Studio]
```

Absender ist das **Studio**, nicht das Produkt: Wortmarke des Studios im
Kopf, `mailSignature()` und Rechtszeile im Fuß, kein GitHub-Ziel.

Beide Blöcke gelten für alle fünf Rollen. Der Abo-Feed enthält laut
`CalendarController:125` genau die eigenen Crew-Einteilungen, die Zuordnung
eingehender Antworten hängt an der HMAC-Reply-Adresse.
`Capability::ViewAllProjects` steht **nicht** in der Schnittmenge aus
`StaffRole::capabilities()` — Zweitfotograf und Assistenz haben es nicht —
deshalb kein Block über "alle Aufträge" und keine Beschreibung der Rolle.

Verworfen: ein Abwesenheiten-Block, weil nach dem Streichen der Reassurance
("dafür braucht es niemanden aus dem Studio") nur Überschrift und Button
übrig blieben. Und ein 2FA-Block, weil Zwei-Faktor eine Pflichtübung ist und
nicht in eine Vorteilsliste gehört.

Keine Grußformel mit dem Namen des Inhabers: die Mail ist systemerzeugt, und
`AppliesStudioMailIdentity` hängt mit `mailSignature()` bereits die Zeile an,
die das Studio selbst steuert.

## 4 Erinnerung: Kommando und Spalte

Migration ergänzt `studio_settings` um `onboarding_reminder_sent_at`
(`timestampTz`, nullable). Einbahnflagge nach dem Muster von
`payment_installments.overdue_notified_at`: einmal gesetzt, nie geleert.

`App\Notifications\Actions\RemindOnboarding`, aufgerufen von
`notifications:remind-onboarding`, täglich 09:00 in `routes/console.php`.
Versendet nur, wenn alle drei Bedingungen halten:

- `onboarding_completed_at` ist NULL
- `onboarding_reminder_sent_at` ist NULL
- der früheste Inhaber (`users`, Rolle `owner`, nach `created_at`) wurde vor
  mindestens zwei Tagen angelegt

Dann geht die Mail an genau diesen Inhaber, danach
`$settings->set('onboarding_reminder_sent_at', now())` über den Resolver,
damit der Redis-Cache invalidiert wird.

## 5 Tests

Pest unter `apps/studio/tests/Feature/`, Container mit `-e APP_SURFACE=admin`:

- `SetupController::store()` versendet `StudioWelcome`
- `complete()` versendet `StudioReady`, und die gerenderte Mail enthält die
  Adresse aus `InboundAddress::catchAll()`
- `RemindOnboarding` versendet bei unfertigem Assistenten und Inhaber älter
  als zwei Tage, und setzt die Flagge
- derselbe Aufruf ein zweites Mal versendet nichts
- bei gesetztem `onboarding_completed_at` versendet er nichts
- `StaffInvited` rendert durch das neue Layout und trägt die Akzentfarbe
- eine Produkt-Mail trägt die Produktmarke, `StaffInvited` die des Studios
- `SetupController::store()` versendet `StudioEmailVerification`
- der Link setzt `email_verified_at` und verbraucht den Datensatz
- der richtige Code tut dasselbe
- ein falscher Code erhöht `attempts`; der sechste Versuch scheitert auch mit
  dem richtigen Code
- ein abgelaufener Datensatz scheitert wie ein verbrauchter und wie ein nie
  existierender

## 6 E-Mail-Bestätigung

Migration legt `users.email_verified_at` (`timestampTz`, nullable) an und die
Tabelle `user_email_verifications`, nach dem Vorbild von
`user_email_changes`: `tenant_id`, `public_id`, `user_id`, `token_hash`
(unique), `code_hash`, `expires_at`, `consumed_at`, `attempts`, Zeitstempel.

`UserEmailVerification` spiegelt `UserEmailChange`: `VALID_FOR_MINUTES = 30`,
`scopeLive()`, `hash()`, `forToken()`, dazu `forCode()`. Scheitern ist
uniform für abgelaufen, verbraucht und nie existiert.

Zwei Wege auf denselben Datensatz:

| Weg | Wo | Warum |
|---|---|---|
| Link | `GET /e-mail-bestaetigen/{token}`, admin-Fläche, öffentlich | die Mail wird oft auf dem Telefon geöffnet, nicht in der angemeldeten Sitzung |
| Code | Feld im ersten Schritt des Assistenten | dort sitzt der Inhaber nach der Anmeldung ohnehin |

**Sicherheit, und der Punkt, an dem diese Tabelle von `user_email_changes`
abweicht:** sechs Ziffern sind 10⁶ Möglichkeiten und damit durchprobierbar.
Der Link braucht keine Bremse, sein Token ist lang; der Code braucht eine.
`attempts` zählt die Fehlversuche je Datensatz, nach fünf gilt er als
verbraucht, und der Endpunkt bekommt zusätzlich ein `RateLimiter`-Fenster je
IP. Der `code_hash` ist Konvention, kein Schutz: bei Datenbankzugriff sind
10⁶ Urbilder in Sekunden durchgerechnet. Die Bremse ist die Verteidigung.

Die Bestätigung ist heute kein Tor. Der Inhaber ist nach
`SetupController::store()` angemeldet und kann arbeiten; der Schritt im
Assistenten lässt sich überspringen. Ob unbestätigte Konten später etwas
nicht dürfen, ist eine Produktentscheidung und steht nicht hier.

## Beobachtet, nicht im Umfang

- `app/Notifications/StaffInvited.php` ist unverändert und verschickt weiter
  "Danach richtest du die Zwei-Faktor-Bestätigung ein."
  `InvitationController:150` sagt, MFA ist opt-in; die eingeladene Person
  landet direkt auf dem Dashboard. Der Text oben ersetzt die Zeile, die
  Klasse muss noch nachgezogen werden. `MailIdentityNotificationTest` und
  `InvitationTest` fassen sie an, prüfen aber Absender und Fußzeile, nicht
  den Text.
- `AppliesStudioMailIdentity` hängt Signatur und Rechtszeile als `->line()`
  an. Beide landen im Rumpf statt im Fuß. Für die Fußzone braucht der Trait
  einen eigenen Slot. Eigenes Issue.
- Ein Anfrageformular gibt es als Produktfunktion nicht. Es existiert
  `POST /api/anfragen` und Beispiel-HTML in `docs/inquiry-form.md`, das ein
  Studio selbst einbauen müsste. Solange kein Screen das Snippet ausgibt,
  kann keine Mail dorthin führen.
- `QuoteSent:56` und `:70` bauen Portal-Links mit `url()` statt
  `config('portal.url')`. Bei getrennten Hosts zeigen sie auf die
  Admin-Fläche. Eigenes Issue.
