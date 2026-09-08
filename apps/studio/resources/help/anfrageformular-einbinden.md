---
titel: Anfrageformular einbinden
beschreibung: Das Formular für die eigene Website und was der Endpunkt damit macht.
art: aufgabe
reihenfolge: 15
faehigkeit: manage_settings
---

Das Formular gehört auf deine Website. Es ist reines HTML mit einem Skript
darin, ohne Rahmen und ohne Stylesheet von hier, und übernimmt das Aussehen
deiner Seite.

```html
<form id="anfrage" novalidate>
  <label>Name <input name="contact_name" required></label>
  <label>E-Mail <input name="contact_email" type="email" required></label>
  <label>Telefon <input name="contact_phone" type="tel"></label>
  <label>Partner/in <input name="partner_name"></label>

  <label>Anlass
    <select name="type" required>
      <option value="wedding">Hochzeit</option>
      <option value="portrait">Porträt</option>
      <option value="other">Sonstiges</option>
    </select>
  </label>

  <label>Datum <input name="date" type="date"></label>
  <label>Ihre Nachricht <textarea name="inquiry_text" rows="5"></textarea></label>

  <label>
    <input name="consent" type="checkbox" required>
    Ich habe die <a href="/datenschutz">Datenschutzerklärung</a> gelesen.
  </label>

  <!-- Bot-Falle. Nicht entfernen, und nicht so benennen, dass ein
       Passwortmanager sie ausfüllt. -->
  <input name="website" tabindex="-1" autocomplete="off" aria-hidden="true"
         style="position:absolute;left:-9999px">

  <button type="submit">Anfrage senden</button>
  <p id="anfrage-danke" hidden>Danke — wir melden uns.</p>
</form>

<script>
  (function () {
    var form = document.getElementById('anfrage')
    var opened = Date.now()

    form.addEventListener('submit', async function (event) {
      event.preventDefault()

      var data = Object.fromEntries(new FormData(form))
      data.consent = form.consent.checked
      data.elapsed = Math.round((Date.now() - opened) / 1000)

      await fetch('%api_url%/api/anfragen', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(data),
      })

      form.reset()
      document.getElementById('anfrage-danke').hidden = false
    })
  })()
</script>
```

## Das Feld `website` bleibt drin

Es ist eine Falle für Bots: unsichtbar für Menschen, ausgefüllt von einfachen
Skripten. Steht irgendetwas darin, wird die Einsendung verworfen. Gib ihm
deshalb auch kein Label, das ein Passwortmanager für ein echtes Feld hält und
automatisch befüllt.

Dieselbe Rolle spielt `elapsed`, die Sekunden zwischen Seitenaufruf und
Absenden. Unter zwei Sekunden hat kein Mensch das Formular gelesen. Wer
JavaScript abgeschaltet hat, kommt trotzdem durch.

## Was der Endpunkt antwortet

Auf alles `202` — angenommen, verworfen, zu schnell, aus einer IP, die schon
fünf Anfragen in einer Viertelstunde geschickt hat. Von außen ist das nicht zu
unterscheiden, und das ist der Zweck: Die Antwort verrät weder, wer schon bei
dir in der Kartei steht, noch ob ein Termin frei ist. Baue also keine
Erfolgsmeldung, die vom Inhalt der Antwort abhängt.

Die eine Ausnahme ist `422`, wenn die Einwilligung fehlt.

Pro E-Mail-Adresse geht eine Anfrage je Viertelstunde durch, pro IP fünf.

## Wo die Anfrage landet

Als neue Anfrage auf dem Board, über denselben Weg wie das Erfassungsformular
im Studio. Der Eingangskanal steht auf *Webformular*, und den kann niemand von
Hand auswählen — deshalb bleibt beantwortbar, wie viele Aufträge deine Website
bringt.

Schreibt jemand, der schon in der Kartei steht, hängt die Anfrage am
bestehenden Kontakt, und der dort gespeicherte Name gilt. Die Einwilligung
wird mit der Adresse der Datenschutzerklärung festgehalten, die im Formular
verlinkt war — der Link muss also auf deine echte Seite zeigen.
