# The public inquiry form

**Story:** C-08 · **Endpoint:** `POST /api/anfragen` on the `api` surface

The studio pastes this into its own website. It is plain HTML with one inline
script — no iframe, no stylesheet, and no script tag pointing back at this
app, so the form is styled by whatever CSS the studio's site already has and
carries nothing that could break when this app deploys.

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

  <!-- Honeypot. Hidden from people, filled by naive bots. Do not remove,
       and do not label it in a way a password manager would autofill. -->
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

      await fetch('https://studio.example/api/anfragen', {
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

Replace `https://studio.example` with the deployment's own host. CORS already
allows any origin for `api/*` (see `config/cors.php` — no route on that
surface answers with a per-origin secret).

## What the endpoint does and does not say

Every outcome returns **`202 {"status":"received"}`**, with one exception:
a submission without consent returns `422`, because that is a fact about the
submission rather than about the studio's data.

Everything else — rate-limited, honeypotted, submitted faster than a human
reads — is dropped silently and answers exactly like an acceptance. The
endpoint never reveals whether an address is already on file, and never
answers whether a date is free: `C-05`'s availability check is staff-only,
and answering it publicly would publish the studio's booked calendar.

| Guard | Limit |
|---|---|
| Per address | 1 submission per 15 minutes |
| Per IP | 5 submissions per 15 minutes |
| Honeypot | any value in `website` drops it |
| Timing | `elapsed` under 2 seconds drops it |

`elapsed` is optional: a visitor with JavaScript disabled still gets through.
Both it and the honeypot are client-supplied and therefore forgeable — they
stop the naive traffic, which is nearly all of it, and the rate limits stop
the rest. No third-party captcha: it would need a key, a DPA and a consent
banner of its own to catch what these catch.

## Where a submission lands

On the lead board as a `new` lead, through `CaptureInquiry` — the same action
the admin capture form calls, with the same validation and the same events. A
public lead is not a second capture path.

- `captured_via` is `web_form`, which no staff member can select by hand
  (`CaptureChannel::staffSelectable()`), so "how many inquiries did the
  website bring us" stays answerable.
- `captured_by_id` is null. No user typed it.
- An address already on file attaches to that contact rather than creating a
  second one, and the stored name wins over the submitted one.
- The consent is recorded in the append-only audit log as
  `lead.consent_recorded`, naming the Datenschutz URL that was shown.
