# Willkommens- und Onboarding-Mails für neue Studios

Status: Entwurf, im Gespräch abgestimmt. Texte am Canvas abgenommen.
Datum: 2026-09-05

## Ziel

Ein frisch angelegtes Studio bekommt heute keine einzige Mail. Diese Spec
schließt die Lücke mit drei Mails an den Inhaber und gibt allen ausgehenden
Mails ein gemeinsames Layout aus MJML.

| Mail | Absender | Auslöser |
|---|---|---|
| Willkommen bei Auri CRM | Produkt | Account-Anlage (`SetupController::store()`) |
| Noch ein paar Einstellungen | Produkt | tägliches Kommando, zwei Tage nach Anlage, Assistent unfertig |
| Deine Adressen bei Auri CRM | Produkt | Abschluss des Assistenten (`StudioOnboardingController::complete()`) |

Nicht im Umfang: Drip-Kampagne über Wochen, Journey-Tabelle mit
Schritt-Status, Mails an Kunden.

## Ausgangslage

- Alle Mails sind heute `MailMessage` im Laravel-Default-Theme.
  `resources/views/vendor/mail` existiert nicht.
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
Lehrerhaltung aus Anweisung, Begründung, Folge und Belohnung. Jeder Satz muss
außerdem etwas tragen, das der Leser auf seinem Bildschirm nicht sieht
(CLAUDE.md, "Prose copy that arrives without a UI"). Deshalb erklärt keine
der drei Mails eine Oberfläche.

**StudioWelcome** — `SetupController::store()`

```
Betreff: Willkommen bei Auri CRM

Hallo {name},

willkommen bei Auri CRM. Ab jetzt liegt alles zu deinen Aufträgen an einem
Ort, von der ersten Anfrage bis zu den fertigen Bildern.

[Studio öffnen]  →  /

Schön, dass du da bist. Viel Spaß damit.
```

Erwähnt die Einrichtung nicht. Die Mail geht bei der Anlage raus, der Inhaber
landet sofort im Assistenten und ist oft durch, bevor er sie öffnet. `/`
stimmt in beiden Fällen, weil `RequireStudioOnboarding` jeden GET auf den
Assistenten umleitet, solange der offen ist.

**StudioSetupPending** — tägliches Kommando, siehe Abschnitt 4

```
Betreff: Noch ein paar Einstellungen

Hallo {name},

um mit Auri CRM richtig durchzustarten, fehlen dir noch ein paar
Einstellungen.

[Weiter einrichten]  →  /einrichtung/studio

Lass dir Zeit damit, dein Studio läuft dir nicht weg.
```

Eine Erinnerung ist ein Stups. Eine Vorfassung nannte den ersten Schritt, den
Grund dafür, die Folge des Aufschiebens und eine erfundene Dauer als Anreiz:
eine Lektion mit Belohnung dran. Eine weitere versicherte "Was du schon
eingetragen hast, ist gespeichert" — der Leser setzt das voraus, und es zu
erwähnen sät erst den Zweifel. Die Wärme trägt der Schlusssatz, der über das
Produkt nichts behauptet.

**StudioReady** — `StudioOnboardingController::complete()`

```
Betreff: Deine Adressen bei Auri CRM

Hallo {name},

deine Anfrage-Adresse steht:

{InboundAddress::catchAll(displayName, inboundSecret)}

Die kannst du auf deiner Website verlinken, wenn du soweit bist.

Dein Kundenportal erreichst du unter {config('portal.url')}.

[Erste Anfrage anlegen]  →  /anfragen/neu

Viel Erfolg mit dem ersten Auftrag.
```

Der Betreff ist aufs Wiederfinden geschrieben. Die Mail trägt die geprägte
Adresse und einen Satz dazu, was damit zu tun ist. Was die Rotation mit
alten Adressen macht, gehört in die Doku, nicht hierher: ein Sonderfall zu
einer Funktion, die der Leser noch nie benutzt hat, wirkt nur deshalb
wertvoll, weil die Mail sonst leer aussieht.

**Zu entscheiden:** Die Adresse enthält das Secret. `docs/inbound-mail.md`
sieht genau diese Adresse für die öffentliche Studio-Website vor, der
Mailversand gibt also nichts Neues preis. Wer das anders bewertet, nennt in
der Mail nur `/posteingang` als Fundort.

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

## Beobachtet, nicht im Umfang

- `StaffInvited` kündigt an: "Danach richtest du die Zwei-Faktor-Bestätigung
  ein." `InvitationController:150` sagt, MFA ist opt-in; die eingeladene
  Person landet direkt auf dem Dashboard. Entweder die Zeile streichen oder
  MFA wieder verpflichtend machen. Eigenes Issue.
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
