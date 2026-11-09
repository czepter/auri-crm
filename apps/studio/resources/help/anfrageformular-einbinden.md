---
titel: Anfrageformular einbinden
beschreibung: Formular für die eigene Website.
art: aufgabe
reihenfolge: 15
faehigkeit: manage_settings
---

```html
<form id="anfrage" novalidate>
  <label>Name <input name="contact_name" required></label>
  <label>E-Mail <input name="contact_email" type="email" required></label>
  <label>Telefon <input name="contact_phone" type="tel"></label>
  <label>Partner <input name="partner_name"></label>

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
  <p id="anfrage-danke" hidden>Danke, wir melden uns.</p>
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

- Endpunkt `%api_url%/api/anfragen` antwortet mit `202`, ohne Einwilligung mit `422`.
- `website` und `elapsed` bleiben im Formular: Bot-Schutz.
- Pro Adresse eine Anfrage je 15 Minuten, pro IP fünf.
