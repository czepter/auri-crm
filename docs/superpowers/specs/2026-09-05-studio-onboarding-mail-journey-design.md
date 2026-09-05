# Willkommens- und Onboarding-Mails für neue Studios

Status: Entwurf, im Gespräch abgestimmt.
Datum: 2026-09-05

## Ziel

Ein frisch angelegtes Studio bekommt heute keine einzige Mail. Diese Spec
schließt die Lücke mit drei Mails an den Owner und gibt allen ausgehenden
Mails dabei ein gemeinsames, gebrandetes Layout aus MJML.

| Mail | Auslöser |
|---|---|
| Willkommen | Account-Anlage (`SetupController::store()`) |
| Einrichtung offen | tägliches Kommando, zwei Tage nach Account-Anlage, Wizard unfertig |
| Startklar | Wizard-Abschluss (`StudioOnboardingController::complete()`) |

Nicht im Umfang: Drip-Kampagne über Wochen, Journey-Tabelle mit
Schritt-Status, Mails an Kunden oder Team.

## Ausgangslage

- Alle Mails sind heute `MailMessage` im Laravel-Default-Theme.
  `resources/views/vendor/mail` existiert nicht, `resources/views` enthält
  vier Dateien, keine davon eine Mail.
- `AppliesStudioMailIdentity` setzt From, Reply-To, BCC und hängt Signatur
  und Rechtsfußzeile als Textzeilen an. Bleibt unverändert.
- `NotificationService` ist der einzige Fan-out-Punkt. Ruhezeiten und
  Präferenzen werden ausschließlich dort geprüft; `Transactional` umgeht
  beides.
- Das Laufzeit-Image hat kein Node. `docker/app/Dockerfile` zieht
  `node:24-alpine` nur für die `assets`-Stufe; die finale `app`-Stufe ist
  reines FrankenPHP.
- `/branding/logo` (`routes/portal.php:207`) ist öffentlich und ohne Session
  erreichbar.
- Routen sind flächengebunden. Ein Prozess mit `APP_SURFACE=admin` kennt die
  Portal-Routen nicht, deshalb `config('portal.url')` statt `route()` — so
  wie `NotifyZipWaiters:25` es bereits macht.
- `studio_settings` wird von der Migration befüllt. `created_at` ist der
  Installationszeitpunkt, nicht der Registrierungszeitpunkt; Anker für
  "vor zwei Tagen" ist deshalb `users.created_at` des Owners.
- CI hat mit `packages/contracts/openapi.json` bereits einen
  Generieren-und-Diffen-Check (`ci.yml:213`). Die MJML-Ausgabe bekommt
  denselben.
- Die Spec vom 2026-09-04 (Mandantenfähigkeit) verschiebt den Auslöser der
  Willkommensmail später von `SetupController` zur Selbstregistrierung. Die
  Notification-Klasse überlebt diesen Umzug, der Aufrufort nicht.

## Entscheidungen

| Frage | Entscheidung | Verworfen |
|---|---|---|
| MJML ausführen | Build-Zeit, Ergebnis eingecheckt, CI-Drift-Check | `spatie/mjml-php` zur Laufzeit (Node ins Laufzeit-Image, Subprozess je Versand für Ausgabe, die nie variiert); nur in der `assets`-Stufe kompilieren (Tests und lokale Entwicklung stünden ohne Views da) |
| Reichweite | `layout` und `button` überschreiben, alles andere fällt auf Laravels eigene Komponenten zurück | Den kompletten Komponentensatz veröffentlichen — zehn Dateien Diff ohne Anlass; `panel` mitgenerieren, das `MailMessage` gar nicht aufrufen kann |
| Branding einspeisen | ein `View::composer` auf `mail::html.layout` | Merge-Daten in jede Notification (vierzehn Dateien für null neuen Nutzen) |
| Platzhalter | Sentinel-Token (`__SLOT__`), nach `mjml` zu Blade ersetzt | `{{ }}` direkt in die MJML-Quelle — ungeklärt, wie MJML das in Attributpositionen escaped |
| Logo | absolute URL auf `config('portal.url')."/branding/logo"` | CID-Embed; `MailMessage` reicht kein `$message` in die Markdown-Views durch |
| Akzentfarbe | inline, aus der MJML-Ausgabe | `themes/default.css` — statische Datei, kann keinen Laufzeitwert je Mandant tragen |
| Erinnerung | tägliches Kommando plus Spalte `onboarding_reminder_sent_at` | verzögerter Job (läge zwei Tage in Redis, ein Flush verlöre die Mail) |
| Präferenzen | nicht in `StudioEvents::ALL` | eine Checkbox "Willkommensmail" auf einer Pane, die man erst nach Erhalt der Mail sieht |
| Mail-Texte | fest im PHP, wie `StaffInvited` | `de.json` — Notification-Texte gehen dort nicht durch |

## 1 Pipeline

Quellen in `apps/studio/resources/mail/`, Ausgabe eingecheckt:

```
resources/mail/layout.mjml  →  resources/views/vendor/mail/html/layout.blade.php
resources/mail/button.mjml  →  resources/views/vendor/mail/html/button.blade.php
```

`mjml` als devDependency in `apps/studio/package.json`, Skript `mail:build`
ruft `scripts/build-mail.mjs`. Das Skript kompiliert jede Quelle und ersetzt
danach die Sentinel:

| Token | wird zu |
|---|---|
| `__SLOT__`, `__HEADER__`, `__FOOTER__`, `__SUBCOPY__` | `{{ $slot }}` usw. |
| `__ACCENT__`, `__STUDIO__`, `__LOGO_URL__` | `{{ $accent }}` usw. |
| `__URL__` | `{{ $url }}` |

`{{ }}` genügt auch für `$slot`: Laravels `e()` lässt `Htmlable` unangetastet,
und Markdown-Slots sind `HtmlString`.

CI-Schritt im `web`-Job, der pnpm bereits hat:

```
pnpm --filter @studio/app mail:build
git diff --exit-code -- apps/studio/resources/views/vendor/mail
```

**Zuerst zu beweisen:** dass Laravel eine teilweise veröffentlichte
Komponentenmenge akzeptiert und für `message`, `header`, `footer` weiter auf
die eigenen Views zurückfällt. Ein Render-Test gegen eine bestehende
Notification zeigt das in einer Zeile.

## 2 Branding im Layout

Ein Composer in `AppServiceProvider`:

```php
View::composer('mail::html.layout', function (View $view) {
    $settings = app(StudioSettingsResolver::class);

    $view->with([
        'studioName' => $settings->displayName() ?? config('app.name'),
        'accent' => $settings->attributes()['accent_color'] ?: '#111111',
        'logoUrl' => $settings->logoPath()
            ? rtrim((string) config('portal.url'), '/').'/branding/logo'
            : null,
    ]);
});
```

Ohne Logo trägt der Header den Studionamen als Wortmarke. Die vierzehn
bestehenden Notifications ändern sich nicht und sind trotzdem gebrandet.

## 3 Die drei Mails

`Notification` plus `MailMessage`, Geschwister von `StaffInvited`, unter
`app/Notifications/Studio/`. Versand über `NotificationService`. Keine ist
`Transactional` — niemand wartet auf diese Mails, und eine Willkommensmail um
03:00 ist genau der Fall, für den es Ruhezeiten gibt.

Die Willkommensmail geht unter `MAIL_FROM_ADDRESS` raus, weil die
Mail-Identität des Studios erst in Wizard-Schritt fünf gesetzt wird. Das ist
richtig so: Absender ist das Deployment, nicht das Studio.

**StudioWelcome** — `SetupController::store()`, nach `Auth::login()`

```
Betreff: Studio angelegt
Hallo {name},
Sechs Schritte bis zum ersten Angebot: Profil, Umsatzsteuer, Dokumente,
Vorlagen, E-Mail, Portal.
[Einrichtung starten] → /einrichtung/studio
Dauer etwa zehn Minuten.
```

**StudioSetupPending** — tägliches Kommando, siehe Abschnitt 4

```
Betreff: Einrichtung offen
Hallo {name},
Ohne Profil und Umsatzsteuer lässt sich kein Angebot schreiben.
[Weiter einrichten] → /einrichtung/studio
```

**StudioReady** — `StudioOnboardingController::complete()`, vor dem Redirect

```
Betreff: Startklar
Hallo {name},
[Erstes Angebot schreiben] → /angebote/neu
Kundenportal: {config('portal.url')}
```

## 4 Erinnerung: Kommando und Spalte

Migration ergänzt `studio_settings` um `onboarding_reminder_sent_at`
(`timestampTz`, nullable). Einbahnflagge, nach demselben Muster wie
`payment_installments.overdue_notified_at`: einmal gesetzt, nie geleert.

`App\Notifications\Actions\RemindOnboarding`, aufgerufen von
`notifications:remind-onboarding`, täglich 09:00 in `routes/console.php`.
Versendet nur, wenn alle drei Bedingungen halten:

- `onboarding_completed_at` ist NULL
- `onboarding_reminder_sent_at` ist NULL
- der früheste Owner (`users`, Rolle `owner`, nach `created_at`) wurde vor
  mindestens zwei Tagen angelegt

Dann geht die Mail an genau diesen Owner, danach
`$settings->set('onboarding_reminder_sent_at', now())` — über den Resolver,
damit der Redis-Cache invalidiert wird.

## 5 Tests

Pest unter `apps/studio/tests/Feature/`, Container mit `-e APP_SURFACE=admin`:

- `SetupController::store()` versendet `StudioWelcome`
- `complete()` versendet `StudioReady`
- `RemindOnboarding` versendet bei unfertigem Wizard und Owner älter als zwei
  Tage, und setzt die Flagge
- derselbe Aufruf ein zweites Mal versendet nichts
- bei gesetztem `onboarding_completed_at` versendet er nichts
- eine bestehende Notification (`StaffInvited`) rendert durch das neue Layout
  und trägt die Akzentfarbe

## Beobachtet, nicht im Umfang

`QuoteSent:56` und `:70` bauen Portal-Links mit `url()` statt
`config('portal.url')`. `config/portal.php` setzt `PORTAL_URL` auf `APP_URL`
zurück, solange nichts anderes gesetzt ist — bei getrennten Hosts zeigen diese
Links auf die Admin-Fläche. Eigenes Issue.
