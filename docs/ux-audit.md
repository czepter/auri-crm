# UX audit — August 2026

A pass over all three surfaces as they ship today, from 895 screenshots taken
by walking the running application rather than by reading the code. The
screens are at `~/Desktop/portal-screens`; the scripts that took them are in
`apps/studio/tests/e2e/shots`.

This is a snapshot with a date on it. The rules that came out of it, and the
drafts that answer it, are in [interface-review.md](interface-review.md) and
`mockups/redesign`.

| Surface | Grade | In one sentence |
|---|---|---|
| **Admin** | 6/10 | A competent, dense tool without a single memorable moment — and unusable on a phone. |
| **Portal** | 4/10 | A status page calling itself a client portal. Shows no photograph and links to the gallery nowhere. |
| **Gallery** | 7/10 Astro, 2/10 gate | The best part of the product, next to a second, empty front door that makes the password a prop. |

---

## Admin

### What carries

The token set is disciplined: one typeface, one accent, consistent radii,
honest number formatting (`tabular-nums`). Density is right — the board and
the lists show a lot without shouting. The six-stage inquiry board with drag
is the strongest interaction in the product.

### What breaks

**Thirty screens, one layout.** Sidebar left, card stack right, kicker above
an h1. Always. Contacts, packages, reports, calendar, settings — visually
indistinguishable. There is no orientation in the space because there is no
space, only a list of lists.

**"Kalender" is not a calendar** (`admin/117-kalender.png`). It is a
chronological list. And the most dominant element on the screen — full
width, top, framed — is the ICS subscription URL with its bearer token in
plain text. The most prominent object on a screen should not be a secret
someone copies once a year.

**"Auswertungen" has no chart** (`admin/131-auswertungen.png`). The single
bar under "Anfragen und Buchungen" runs into the neighbouring column and
collides with the figure `1`. The liquidity table is twelve rows of `—`. A
reporting screen made of four KPI tiles and three tables, two of them empty,
is a database view.

**Status redundancy.** On `admin/flows/013` the booking state appears three
times on one screen: a chip top right (`GEBUCHT`), a chip under the title
(`● Gebucht`), a whole tile in the overview tab (`STAND / Neu / Noch nicht
gebucht`), plus the header button `Als gebucht markieren`. Four elements,
one piece of information.

**Text runs out of its tiles.** `admin/332`: `HAUPTTERMIN` is clipped at the
right edge, `Ausgeblendet` overflows its dashed box. In the calendar the
crew initials `CB` render as `CI` because the chip width is fixed. These are
not edge cases — they are the defaults of the demo data.

**Half the page is empty.** `admin/332-auftrag-uebersicht.png` ends at
roughly 45 % height; the right column is dead from `Aktivität: Nichts
passiert.` onward. At 1440×900 it looks like a page that failed to finish
loading.

**No action hierarchy.** `Erledigt`, `Anrufen`, `Vertrag senden` on the
dashboard are all the same small ghost chip at the same size as the
surrounding meta text. There is exactly one filled button per screen and it
is usually `Anfrage anlegen` — not the action that is pending.

**Mobile is a total failure** (`admin/phone/466-anfragen-board-phone.png`).
At 390 px the six board columns stack on top of each other, text over text,
unreadable. The 260 px sidebar stays fixed and eats two thirds of the width.
There is no breakpoint. For a tool a photographer holds on the day of a
shoot, that is not a compromise.

---

## Portal

This is the weakest surface, and not because of details.

**There is no way to the pictures.** Verified: `Projects/Show.vue`,
`Home.vue` and `Portal\Http\Controllers\CaseController` do not contain
the word gallery once. On `portal/014-projekt.png` two shoots are marked
`GELIEFERT` and the client can go nowhere from there. A couple signs in to a
portal to see photographs. The portal knows nothing about the photographs.

**It shows no image at all.** A photography client portal whose entire
visual identity is an 8 px dot beside the studio name. No cover, no shoot
thumbnail, no texture. White ground, grey type, three cards.

**"Hallo Meier".** The contact is called `Meier & Kowalski`. The greeting
takes the first word. For every couple — so for every wedding — the first
line the client reads is wrong.

**The only actions are the weakest elements.** `Nachrichten →`, `Vertrag →`,
`Zahlungen →`: bare grey text links, stacked, below the fold of attention.
Above them `Shootings` and `4 Termine` sit as two unstyled lines with no
size difference, which reads like forgotten markup.

**Empty states are the norm, not the exception.** `portal/034-projekt-
zahlungen` before the fix: a heading and `Kein Zahlungsplan vorhanden.` on
1800 px of white. The portal has six screens and on a typical day three of
them are empty.

**The header overflows on mobile** (`portal/phone/057`): `Abmelden` is cut
off at the right edge, no wrapping, no menu. This is the surface most
certain to be opened on a phone.

---

## Gallery

**The Astro part is the best thing in the product.**
`gallery/guest/001-astro-raster.png`: dark theme, masonry with varying tile
heights, images without frames, the title small and held back. The lightbox
with filmstrip, favourite and a comment rail beside it is the only place in
the whole product with a recognisable stance. Here the photograph gets the
stage; everywhere else the form does.

**Next to it stands a second, empty front door.**
`gallery/gates/024-ansicht-freigeschaltet.png`: the guest types the password
and lands on `Bilderübersicht noch nicht verfügbar.` That is the moment the
entire gate mechanism works toward, and it is a dead end.

**The password protects nothing.** Measured with a view password set and no
session at all:

```
tiles in the Astro gallery despite the password: 33
assets over the open API:                        33
```

`/api/galleries/{id}` carries no gate — `GalleryReadController` says so in
its own docblock. Anyone with the gallery id sees everything. The password
field is therefore theatre, and worse than no password: it promises the
studio a protection that does not exist.

**Gate screen details.** Content clings to the top of an 1800 px black area,
no vertical centring. The input has `--line:#241F1C` on `--bg:#0E0C0B` — on
an uncalibrated laptop the border is invisible and the guest sees a label
and a void. And the screen knows the date and venue of the shoot (the
controller passes both) but shows neither.

---

## Across all three

**Typography.** Instrument Sans for everything. From 10 px kickers to 24 px
h1, weights 400 to 700. That is a range of little more than two doublings —
there is not one typographic moment, no display size, no second voice. The
typeface is well chosen and used on a low flame.

**Colour.** White, `#191919`, a rust `#B44E22`, red for warnings, green for
booked. The accent carries primary action, brand dot and gallery CTA at
once. Everything else is greyscale. Disciplined and entirely
interchangeable — the same palette sits under half of all SaaS dashboards.

**Motion.** Practically none. `tokens.css` has `transition: background .12s`
on the navigation. No page build, no stagger, no feedback on save beyond the
re-render. The application feels like a server-rendered form from 2012,
which technically it is not.

**The comment in `tokens.css` names the problem itself:** *"portal — same
bones, more air."* That is exactly the mistake. An administrative tool for
the operator and a keepsake for a wedding couple must not have the same
bones. The gallery broke away from it, which is why it is the only surface
that works.

---

## What I would repair first

1. **Link the gallery into the portal**, and either point the portal gate
   screen at the real grid or drop it. Two front doors, one of them empty,
   is the most expensive confusion in the product.
2. **Put `/api/galleries/{id}` behind the gate.** While that is open, the
   password field is a lie told to the studio.
3. **Two breakpoints in the admin** (sidebar to drawer, board to horizontal
   scroll). The most important screen is currently destroyed on a phone.
4. **Raise the portal visually:** an image from the project as its head, the
   three links as cards with state (`3 neue Nachrichten`, `Vertrag
   unterschrieben`), a greeting that does not split names.
5. **Auswertungen:** a real chart, the colliding bar gone, empty tables
   replaced by an honest empty state.
6. **Resolve the status redundancy:** one place per state, and the button
   beside it shows the next action rather than the same state again.
7. **Kalender:** a month grid instead of a list, and the ICS token moved
   into settings where it belongs.

---

## Why the three surfaces cannot share an answer

Admin needs **density** — much at a glance. The portal needs **trust** —
little, but warm and expensive. The gallery needs to **disappear** — the
interface should go away so the picture can be there.

The product built one answer for all three, cards on white, and the gallery
is only good because it is the one that broke out and got its own rules
under `[data-theme="gallery"]`. The way forward is to allow the portal the
same break.
