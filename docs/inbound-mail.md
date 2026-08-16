# Inbound mail

**Story:** E-12 · **Screen:** `/posteingang`

The studio never hands over a mailbox credential. This app's own hosting owns
an inbound domain, and every inbound address is minted from a per-tenant
secret held in `studio_settings.inbound_secret`.

```
studio-name.<secret>@<inbound-domain>        the account catch-all
t.<thread-public-id>.<hmac>@<inbound-domain> one thread's reply address
```

The handle is the studio's own name, slugged — this is the address a studio
prints on its website. It is not the credential; the `<secret>` half is.

The thread address goes out as `Reply-To` on every mail a staff reply sends
(E-11), so a client pressing Reply in any mail client threads exactly.

**Rotation** (Inhaber only, `/posteingang`) replaces the secret in one write.
Every address ever issued stops verifying, and both shapes then fail *closed*
into the triage tray rather than open.

## Two adapters, one ingest

Everything that matters — the HMAC check, the three-condition threading rule,
triage, quarantine, idempotency on `Message-ID` — lives in
`App\Engagement\Actions\IngestInboundMessage`, which takes a flat array. How
that array is produced is an adapter, and there are two.

### IMAP polling — the default

`php artisan mail:poll-inbound`, scheduled every minute. Reads unread mail
from the catch-all mailbox **this deployment owns**, parses it with
`App\Engagement\Support\InboundMimeMessage`, and marks it read only after the
ingest returns — a crash mid-ingest leaves it unread for the next pass, and
picking it up twice is harmless because ingest is idempotent.

```
MAIL_INBOUND_DOMAIN=in.auri-crm.de
MAIL_INBOUND_AUTHSERV_ID=mx.auri-crm.de
MAIL_INBOUND_IMAP_HOST=imap.auri-crm.de
MAIL_INBOUND_IMAP_PORT=993
MAIL_INBOUND_IMAP_ENCRYPTION=ssl
MAIL_INBOUND_IMAP_USERNAME=catchall@auri-crm.de
MAIL_INBOUND_IMAP_PASSWORD=…
MAIL_INBOUND_IMAP_FOLDER=INBOX
```

Polling rather than IMAP IDLE: a minute of latency on a client's reply is
nothing, and a daemon somebody has to supervise is considerably more than
nothing.

### Provider webhook — the alternative

`POST /api/mail/eingang`, HMAC over the raw body in `X-Inbound-Signature`,
verified **before** the body is parsed. For a deployment that would rather
let a provider (Postmark, Mailgun, …) do the receiving.

```
MAIL_INBOUND_SIGNING_SECRET=…
```

Unset, the endpoint refuses everything rather than accepting anything: a
deployment that has not provisioned this has no business ingesting through it.

## Authentication results

`SPF`/`DKIM`/`DMARC` decide whether a delivery is quarantined. Over IMAP they
come from the `Authentication-Results` header, and **only the topmost one
counts** — a sender is free to put a forged `dmarc=pass` in the message they
send us, and our own MTA's verdict is prepended above it.

Set `MAIL_INBOUND_AUTHSERV_ID` to the identity your MTA writes. The topmost
header must then also carry it, or no verdict is trusted at all — an
unverifiable claim is not a passing check.

**This means the MX host must actually perform the checks.** Without them
there is no `Authentication-Results` header, every verdict reads null, and
the quarantine branch never fires — mail still routes, but the spoofing guard
is inert.

## What happens to a delivery

Resolution comes first. `InboundRecipient` verifies the recipient against
every account's secret **before a single field is written**, and only two
shapes resolve: a `t.` thread address, or an account's catch-all.

| condition | outcome |
|---|---|
| recipient resolves to no studio account | **rejected** — no row, nothing about the sender stored |
| `t.` address + valid HMAC + `From:` matches a thread participant | threaded, becomes a `Message` |
| DMARC failed | **quarantined**, visible, never threaded and never attachable by hand |
| resolved, but names no conversation | **triage**, a human attaches it at `/posteingang` |

### Why rejection is not "triage for unknown senders"

A stranger writing to an address that belongs to nobody has no relationship
with any studio here — no consent, no contract, no legitimate interest. Filing
what they wrote into a tray "so a human can look" is processing personal data
on the strength of it having arrived, which is not a lawful basis. So the
sender's address, the subject and the body are never written down.

What *is* kept is a log line naming the address it was sent **to** — ours, not
anybody's personal data — so "why is mail to this address bouncing" stays
answerable. Deliberately a log line rather than an `audit_logs` row, because
that table is tenant-scoped and this delivery belongs to no tenant.

It is also a tenancy guard, not only a privacy one. `inbound_messages` takes
its `tenant_id` from the resolved recipient rather than from the ambient
tenant — without that, a delivery addressed to nobody would be filed under
whichever studio the poller happened to be running as.

The IMAP poller **deletes** a rejected message from the mailbox. Leaving it
sitting there read would be the same personal data in a different store.

**Rejection is best done one layer earlier**, at the MTA's `RCPT TO`, so the
mail is never accepted at all. That needs a recipient-validation callback on
the mail host; until one exists, this is the first moment the application
controls.

Everything that *is* accepted is kept whatever became of it — every accepted
delivery is an `inbound_messages` row, so "the client says they replied and we
never saw it" has somewhere to be answered.

Threading needs all three conditions. An address is trivially forged, so
attaching on `From:` alone would let a stranger post into a client's
conversation and be read by the studio as that client.

## Lead-time

MX records for the inbound domain, and a mailbox on it. Nothing here works
without them, and with them unset the ingest falls closed into triage rather
than pretending — asserted by a test.
