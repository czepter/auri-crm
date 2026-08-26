# Galerie: der Gast bekommt einen Namen

Stand 2026-08-25. Betrifft `apps/gallery` (Astro-Betrachter) und die api-Fläche
in `apps/studio`.

## Ausgangslage

Ein Gast ist heute eine zufällige UUID in `localStorage` — `gallery_guest_id`,
gemünzt von `apps/gallery/src/lib/favorites.ts` beim ersten Herz. Daran hängt
alles: `asset_favorites.guest_identifier`, `asset_comments.guest_identifier`.

Drei Folgen:

- **Gerätewechsel löscht alles.** Wer die Galerie auf dem Telefon markiert und
  am Rechner öffnet, findet nichts wieder. Dasselbe nach geleertem Speicher.
- **Jeder Kommentar heißt „Gast".** `comments.ts` schreibt es wörtlich hin:
  die App erfindet keine Identität, die sie nie erhebt. Zwölf Kommentare unter
  einem Bild sind zwölfmal derselbe Absender.
- **Das fertige ZIP hat kein Ziel.** `download_events.requester_identifier`
  trägt die Portal-Session-ID. Ein asynchroner Bau kann niemandem sagen, dass
  er fertig ist.

Vorhanden ist mehr, als es scheint: `gallery_email_verifications` mit
`RequestGalleryEmailVerification`, `ConsumeGalleryEmailVerification` und
`GalleryEmailVerificationLink` — benutzt aber nur vom Download und vom
Portal-Gate. Der Astro-Betrachter kennt davon nichts.

## Rahmen

Die api-Fläche hat keine Session und keine Cookies (`routes/api.php` fährt die
Standard-`api`-Middleware). Der Betrachter trägt trotzdem schon ein
HttpOnly-Cookie auf seiner eigenen Herkunft: den signierten Ansichtspass
`gallery_pass_{id}`, vom Server geholt und als Bearer zurückgeschickt
(`src/lib/viewPass.ts`, `App\Gallery\Support\GalleryViewPass`).

Die Identität geht denselben Weg. Kein neuer Mechanismus.

## Entscheidungen

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Was Identität stiftet | Eine bestätigte E-Mail-Adresse. Ohne sie kein Herz, kein Kommentar, kein Download |
| 2 | Wie bestätigt wird | Sechs Ziffern in der Mail, Eingabefeld auf der Seite — dazu derselbe Code als Link. Die Mail auf dem Telefon lesen und am Rechner sitzen ist bei einer Hochzeitsgalerie der Normalfall; abtippen geht dann, klicken auch |
| 3 | Wie fest der Code ist | 30 Minuten gültig (`GalleryEmailVerification::VALID_FOR_MINUTES`), fünf Fehlversuche, dann verfällt er. Bremse auch auf das *Anfordern*, sonst holt sich ein Angreifer beliebig viele frische Codes. Gezählt wird je Adresse und Galerie, **nicht je IP**: der Betrachter rendert serverseitig, alle Gäste kämen als eine Adresse an — derselbe Fehler wie R44 |
| 4 | Was die Kennung wird | Ein Hash der bestätigten Adresse. Dieselbe Adresse auf Telefon und Rechner ergibt dieselbe Kennung, der Verlauf lädt überall |
| 5 | Was mit anonymen Markierungen passiert | Beim Bestätigen werden die Zeilen dieser UUID auf die Adress-Kennung umgeschrieben. Wer erst zehn Bilder markiert und dann zur Anmeldung aufgefordert wird, darf die zehn nicht verlieren — sonst meldet er sich nicht an |
| 6 | Kollision beim Umschreiben | Liegt unter der Adresse schon ein Herz für dasselbe Bild (`asset_favorites_unique_guest_mark`), wird die anonyme Zeile gelöscht statt umgeschrieben. Sichtbar ändert sich nichts: markiert war es vorher und nachher. Kommentare haben keinen solchen Index und wandern vollständig mit |
| 7 | Ein Mailschritt oder zwei | Einer. Wer sich fürs Markieren angemeldet hat, ist auch für den Download angemeldet. Zweimal nach derselben Adresse zu fragen liest sich wie ein Fehler |
| 8 | Was gesperrt ist | Herz, Kommentar und Download. Ansehen bleibt frei |
| 9 | Was aus `requires_verified_email` wird | Bleibt, und entscheidet genau eine Sache: ob die Adresse vor dem Download **bestätigt** sein muss. Eine **Zustelladresse** verlangt der Download unabhängig davon — sonst hat das fertige ZIP kein Ziel. Wer schon markiert oder kommentiert hat, ist ohnehin bestätigt; für den ändert der Schalter nichts |
| 17 | Der angemeldete Kunde | Wird nicht nach einer Adresse gefragt: sie steht in seinem Konto, und ihn erneut zu fragen wäre dieselbe Frage zum zweiten Mal. Verlangt das Studio einen Nachweis, gilt das nicht — dann ist die Adresse ein Tor und kein Zustellziel |
| 18 | Wer auf ein Archiv wartet | Eigene Tabelle `zip_build_waiters`. Ein `ZipBuild` ist über `(gallery_version_id, scope, size)` eindeutig, damit zwei Gäste nicht zweimal 14 GB packen lassen — genau deshalb kann die Zustelladresse nicht an ihm hängen |
| 19 | Wann die Nachricht geht | In `BuildGalleryZip`, sobald der Status auf `Ready` steht: dort wird die Tatsache wahr. `notified_at` steht vor dem Versand, damit ein Bau, der nach einem Fehlschlag erneut durchläuft, niemanden zweimal anschreibt |
| 20 | Zweimal bestätigen, Betrachter und Portal | So gewollt. Es sind zwei Anwendungen auf zwei Hosts: das Cookie liegt hier, die Sitzung dort, und ein Cookie überquert diese Grenze nicht. Den Pass als Abfrageparameter mitzureichen lehnt dieses Repo für den Ansichtspass ausdrücklich ab („a pass in a URL ends up in an access log, a Referer and a bookmark"), und ein Einmal-Token wäre Maschinerie für eine Grenze, die es geben soll. Geteilt wird die Zeile in `gallery_guests`, nicht die Anmeldung |
| 21 | Worauf der Link zeigt | Auf den Download-Schirm, nicht auf ein Band. Die Bänder tragen signierte Adressen mit 24 Stunden Frist, die eine Mail überleben würde, und wie viele es sind, weiß der Schirm besser |
| 10 | Reihenfolge beim Download | Erst die PIN, wo das Studio eine gesetzt hat, dann die Adresse. Von jemandem eine Adresse einzusammeln, den man gleich abweist, ist Erhebung ohne Zweck |
| 11 | Reichweite der Anmeldung | Je Galerie. Eine Bestätigung, die für die Galerie einer anderen Hochzeit mitgilt, ist eine Einwilligung, die niemand gegeben hat. Kennt der Browser schon eine Adresse, steht sie im Feld — Tippen gespart, Bestätigung trotzdem |
| 12 | Was am Kommentar steht | Der Name, nicht die Adresse. Eine Galerie mit fünfzig Gästen wäre sonst eine Adressliste. Beim Anmelden zwei Felder, „Name" und „E-Mail"; der Name kommt aus dem Teil vor dem `@` vorbelegt (Punkte und Unterstriche werden Leerzeichen, angehängte Ziffern fallen weg, Anfangsbuchstaben groß) und bleibt änderbar |
| 13 | Der Indikator | In der Fußzeile neben „22 Bilder": der Name und *Abmelden* |
| 14 | Was Abmelden löscht | Cookie **und** `gallery_guest_id`. Der geteilte Rechner ist der Fall, für den der Knopf da ist — bliebe die UUID liegen, erbte der Nächste die Markierungen von vor der Anmeldung |
| 15 | Wo die Identität wohnt | Neue Tabelle `gallery_guests`: `gallery_id`, `email`, `name`, `identifier`, `confirmed_at`. `gallery_email_verifications` bleibt, was es ist — ein Versuchsprotokoll, eine Zeile je angefordertem Code |
| 16 | Wie sie reist | Signiertes Token von der api, HttpOnly-Cookie auf der Herkunft des Betrachters, Bearer bei jedem Aufruf. Genau der Weg, den `GalleryViewPass` schon geht |

## Datenmodell

### Neu: `zip_build_waiters`

`zip_build_id`, `gallery_guest_id`, `notified_at`. Eindeutig über
`(tenant_id, zip_build_id, gallery_guest_id)`.

### Neu: `gallery_guests`

| Spalte | |
|---|---|
| `gallery_id` | Fremdschlüssel, `cascadeOnDelete` |
| `email` | Die bestätigte Adresse |
| `name` | Was am Kommentar steht |
| `identifier` | Hash der Adresse — der Wert, den `asset_favorites` und `asset_comments` tragen |
| `confirmed_at` | Erst gesetzt, wenn der Code stimmte |

Eindeutig über `(tenant_id, gallery_id, email)`. `identifier` bekommt einen
Index, weil jede Herz- und Kommentarabfrage darüber sucht.

### Geändert

- `download_events.requester_identifier` trägt künftig denselben Hash und
  meint damit zum ersten Mal einen Menschen statt einer Session-ID.

## Nicht enthalten

- Ein Konto für Gäste. Eine bestätigte Adresse ist kein Login
- Zusammenführen zweier Adressen desselben Menschen
- Namensänderung rückwirkend an alten Kommentaren — der Name wird beim
  Anzeigen geholt, nicht in die Zeile geschrieben, also stellt sich die Frage
  nicht
